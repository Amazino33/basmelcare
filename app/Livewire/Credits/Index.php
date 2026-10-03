<?php

namespace App\Livewire\Credits;

use App\Livewire\Concerns\DeniesAuditorWrites;

use App\Models\CreditPayout;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use DeniesAuditorWrites;
    use Toast, WithPagination;

    public string $search = '';

    public ?int $payingCustomerId = null;
    public string $payout_amount  = '';
    public bool $payoutModal      = false;
    public bool $payoutSuccess    = false;
    public ?int $lastPayoutId     = null;

    // Adjustment / Correction
    public bool $adjustModal      = false;
    public ?int $adjustCustomerId = null;
    public string $new_balance    = '';
    public string $adjust_reason  = '';

    public function openPayout(int $customerId): void
    {
        $customer = Customer::findOrFail($customerId);

        $this->payingCustomerId = $customerId;
        $this->payout_amount    = (string) $customer->credit_balance;
        $this->payoutSuccess    = false;
        $this->lastPayoutId     = null;
        $this->payoutModal      = true;
    }

    public function recordPayout(): void
    {
        if ($this->blockedAsAuditor()) return;

        $customer = Customer::findOrFail($this->payingCustomerId);

        $this->validate([
            'payout_amount' => ['required', 'numeric', 'min:0.01', 'max:' . $customer->credit_balance],
        ]);

        $amount        = (float) $this->payout_amount;
        $balanceBefore = (float) $customer->credit_balance;

        $customer->decrement('credit_balance', $amount);

        $payout = CreditPayout::create([
            'customer_id'    => $customer->id,
            'amount'         => $amount,
            'balance_before' => $balanceBefore,
            'balance_after'  => max(0, $balanceBefore - $amount),
            'cashier_id'     => auth()->id(),
        ]);

        $this->lastPayoutId  = $payout->id;
        $this->payoutSuccess = true;
        $this->reset(['payingCustomerId', 'payout_amount']);
        $this->success('₦' . number_format($amount, 2) . ' paid out to ' . $customer->name . '.');
    }

    public function canManageCredit(): bool
    {
        $roles = auth()->user()->role ?? [];
        return (bool) array_intersect($roles, ['admin', 'branch_manager']);
    }

    public function openAdjustment(int $customerId): void
    {
        if (! $this->canManageCredit()) {
            $this->error('Only a branch manager or admin can adjust credit balances.');
            return;
        }

        $customer = Customer::findOrFail($customerId);

        $this->adjustCustomerId = $customerId;
        $this->new_balance      = (string) $customer->credit_balance;
        $this->adjust_reason    = '';
        $this->adjustModal       = true;
    }

    public function saveAdjustment(): void
    {
        if ($this->blockedAsAuditor()) return;

        if (! $this->canManageCredit()) {
            $this->error('Only a branch manager or admin can adjust credit balances.');
            return;
        }

        $customer = Customer::findOrFail($this->adjustCustomerId);

        $this->validate([
            'new_balance'   => ['required', 'numeric', 'min:0'],
            'adjust_reason' => ['required', 'string', 'max:255'],
        ]);

        $oldBalance = (float) $customer->credit_balance;
        $newBalance = round((float) $this->new_balance, 2);

        if (abs($newBalance - $oldBalance) < 0.001) {
            $this->error('The new balance is identical to the current balance.');
            return;
        }

        $managerName = auth()->user()->name ?? 'Branch Manager';

        DB::transaction(function () use ($customer, $oldBalance, $newBalance, $managerName) {
            $customer->update(['credit_balance' => $newBalance]);

            // Non-cash adjustment: amount is 0.00 so drawer cash calculation is untouched
            CreditPayout::create([
                'customer_id'    => $customer->id,
                'amount'         => 0.00,
                'balance_before' => $oldBalance,
                'balance_after'  => $newBalance,
                'cashier_id'     => auth()->id(),
                'note'           => "Adjustment by {$managerName}: {$this->adjust_reason} (Balance corrected from ₦" . number_format($oldBalance, 2) . " to ₦" . number_format($newBalance, 2) . ")",
            ]);
        });

        $this->adjustModal = false;
        $this->reset(['adjustCustomerId', 'new_balance', 'adjust_reason']);
        $this->success("Credit balance for {$customer->name} corrected to ₦" . number_format($newBalance, 2) . ".");
    }

    public function voidPayout(int $payoutId): void
    {
        if ($this->blockedAsAuditor()) return;

        if (! $this->canManageCredit()) {
            $this->error('Only a branch manager or admin can void payouts.');
            return;
        }

        $payout = CreditPayout::with('customer')->findOrFail($payoutId);

        if (str_starts_with($payout->note ?? '', '[VOIDED]')) {
            $this->warning('This payout has already been voided.');
            return;
        }

        if ($payout->amount <= 0.001) {
            $this->warning('Adjustments cannot be voided. Adjust the balance instead.');
            return;
        }

        $managerName = auth()->user()->name ?? 'Branch Manager';
        $amount      = (float) $payout->amount;

        DB::transaction(function () use ($payout, $amount, $managerName) {
            if ($payout->customer) {
                $payout->customer->increment('credit_balance', $amount);
            }

            $payout->update([
                'note'   => "[VOIDED by {$managerName}] " . ($payout->note ?? 'Mistaken payout reversed'),
                'amount' => 0.00,
            ]);
        });

        $this->success("Payout #{$payout->id} voided. ₦" . number_format($amount, 2) . " restored to customer balance and drawer reconciled.");
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $customers = Customer::query()
            ->when($this->search, function ($q) {
                $q->where(fn($sub) =>
                    $sub->where('name', 'like', "%{$this->search}%")
                        ->orWhere('phone', 'like', "%{$this->search}%")
                );
            }, function ($q) {
                $q->where('credit_balance', '>', 0);
            })
            ->orderByDesc('credit_balance')
            ->paginate(20);

        $totalCredit = Customer::where('credit_balance', '>', 0)->sum('credit_balance');
        $totalCount  = Customer::where('credit_balance', '>', 0)->count();

        $payingCustomer = $this->payingCustomerId
            ? Customer::find($this->payingCustomerId)
            : null;

        $adjustCustomer = $this->adjustCustomerId
            ? Customer::find($this->adjustCustomerId)
            : null;

        $history = CreditPayout::with('customer', 'cashier')
            ->when($this->search, fn($q) => $q->whereHas('customer', fn($c) =>
                $c->where('name', 'like', "%{$this->search}%")
                  ->orWhere('phone', 'like', "%{$this->search}%")
            ))
            ->latest()
            ->paginate(20, ['*'], 'historyPage');

        return view('livewire.credits.index', [
            'customers'      => $customers,
            'totalCredit'    => $totalCredit,
            'totalCount'     => $totalCount,
            'payingCustomer' => $payingCustomer,
            'adjustCustomer' => $adjustCustomer,
            'history'        => $history,
        ]);
    }
}
