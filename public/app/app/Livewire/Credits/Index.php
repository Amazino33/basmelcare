<?php

namespace App\Livewire\Credits;

use App\Models\CreditPayout;
use App\Models\Customer;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast, WithPagination;

    public string $search = '';

    public ?int $payingCustomerId = null;
    public string $payout_amount  = '';
    public bool $payoutModal      = false;
    public bool $payoutSuccess    = false;
    public ?int $lastPayoutId     = null;

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

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $customers = Customer::where('credit_balance', '>', 0)
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%"))
            ->orderByDesc('credit_balance')
            ->paginate(20);

        $totalCredit = Customer::where('credit_balance', '>', 0)->sum('credit_balance');
        $totalCount  = Customer::where('credit_balance', '>', 0)->count();

        $payingCustomer = $this->payingCustomerId
            ? Customer::find($this->payingCustomerId)
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
            'history'        => $history,
        ]);
    }
}
