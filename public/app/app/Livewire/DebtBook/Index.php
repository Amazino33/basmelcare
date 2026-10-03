<?php

namespace App\Livewire\DebtBook;

use App\Livewire\Concerns\DeniesAuditorWrites;
use App\Models\Debt;
use App\Models\DebtPayment;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use DeniesAuditorWrites;
    use Toast, WithPagination;

    public string $search = '';

    public string $statusFilter = 'outstanding';

    // Payment form
    public ?int $payDebtId = null;

    public string $pay_amount = '';

    public string $pay_method = 'cash';

    public string $pay_note = '';

    public bool $payModal = false;

    public ?int $lastDebtPaymentId = null;

    // Details
    public ?int $viewDebtId = null;

    public bool $detailsDrawer = false;

    // Debt adjustment
    public bool $adjustDebtModal = false;
    public ?int $adjustDebtId = null;
    public string $new_amount_owed = '';
    public string $debt_adjust_reason = '';

    public function openPayment($debtId)
    {
        $this->payDebtId = $debtId;
        $debt = Debt::findOrFail($debtId);
        $this->pay_amount = (string) $debt->balance;
        $this->reset(['pay_method', 'pay_note']);
        $this->pay_method = 'cash';
        $this->payModal = true;
    }

    public function recordPayment()
    {
        if ($this->blockedAsAuditor()) {
            return;
        }

        $this->validate([
            'pay_amount' => 'required|numeric|min:0.01',
            'pay_method' => 'required|in:cash,card,transfer',
            'pay_note' => 'nullable|string|max:500',
        ]);

        $debt = Debt::findOrFail($this->payDebtId);
        $amount = (float) $this->pay_amount;

        if ($amount > $debt->balance) {
            $this->error('Payment exceeds outstanding balance (₦'.number_format($debt->balance, 2).').');

            return;
        }

        $debtPayment = DebtPayment::create([
            'debt_id' => $debt->id,
            'amount' => $amount,
            'payment_method' => $this->pay_method,
            'received_by' => auth()->id(),
            'note' => $this->pay_note,
        ]);

        $debt->increment('amount_paid', $amount);

        if ($debt->fresh()->balance <= 0) {
            $debt->update(['status' => 'paid']);
        } else {
            $debt->update(['status' => 'partial']);
        }

        $this->lastDebtPaymentId = $debtPayment->id;
        $this->payModal = false;
        $this->success('Payment of ₦'.number_format($amount, 2).' recorded.');
        $this->reset(['payDebtId', 'pay_amount', 'pay_method', 'pay_note']);
        $this->dispatch('open-debt-receipt', id: $debtPayment->id);
    }

    public function viewDetails($debtId)
    {
        $this->viewDebtId = $debtId;
        $this->detailsDrawer = true;
    }

    public function canManage(): bool
    {
        return (bool) array_intersect(auth()->user()->role ?? [], ['admin', 'branch_manager']);
    }

    public function openAdjustDebt(int $debtId): void
    {
        if (! $this->canManage()) {
            $this->error('Only a branch manager or admin can adjust debts.');
            return;
        }

        $debt = Debt::findOrFail($debtId);
        $this->adjustDebtId       = $debtId;
        $this->new_amount_owed    = (string) $debt->amount_owed;
        $this->debt_adjust_reason = '';
        $this->adjustDebtModal    = true;
    }

    public function saveAdjustDebt(): void
    {
        if ($this->blockedAsAuditor()) return;
        if (! $this->canManage()) {
            $this->error('Only a branch manager or admin can adjust debts.');
            return;
        }

        $debt = Debt::findOrFail($this->adjustDebtId);

        $this->validate([
            'new_amount_owed'    => ['required', 'numeric', 'min:0'],
            'debt_adjust_reason' => ['required', 'string', 'max:255'],
        ]);

        $newOwed = round((float) $this->new_amount_owed, 2);
        $oldOwed = (float) $debt->amount_owed;

        $debt->amount_owed = $newOwed;
        $balance = max(0, $newOwed - (float) ($debt->amount_paid ?? 0));
        $debt->status = $balance <= 0.001 ? 'paid' : ((float) ($debt->amount_paid ?? 0) > 0 ? 'partial' : 'unpaid');
        $debt->save();

        $this->adjustDebtModal = false;
        $this->reset(['adjustDebtId', 'new_amount_owed', 'debt_adjust_reason']);
        $this->success("Debt #{$debt->id} amount owed adjusted from ₦" . number_format($oldOwed, 2) . " to ₦" . number_format($newOwed, 2) . ".");
    }

    public function voidDebtPayment(int $paymentId): void
    {
        if ($this->blockedAsAuditor()) return;
        if (! $this->canManage()) {
            $this->error('Only a branch manager or admin can void debt payments.');
            return;
        }

        $payment = DebtPayment::with('debt')->findOrFail($paymentId);

        if (str_starts_with($payment->note ?? '', '[VOIDED]')) {
            $this->warning('This payment has already been voided.');
            return;
        }

        $managerName = auth()->user()->name ?? 'Branch Manager';
        $amount = (float) $payment->amount;
        $debt = $payment->debt;

        \Illuminate\Support\Facades\DB::transaction(function () use ($payment, $debt, $amount, $managerName) {
            $debt->amount_paid = max(0, (float) ($debt->amount_paid ?? 0) - $amount);
            $debt->status = ((float) $debt->amount_owed - (float) $debt->amount_paid) <= 0.001 ? 'paid' : ((float) $debt->amount_paid > 0 ? 'partial' : 'unpaid');
            $debt->save();

            $payment->update([
                'note' => "[VOIDED by {$managerName}] " . ($payment->note ?? 'Mistaken payment voided'),
                'amount' => 0.00,
            ]);
        });

        $this->success("Debt payment #{$payment->id} voided and reversed.");
    }

    public function render()
    {
        $headers = [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'customer.name', 'label' => 'Customer'],
            ['key' => 'sale_id', 'label' => 'Sale'],
            ['key' => 'amount_owed', 'label' => 'Owed'],
            ['key' => 'amount_paid', 'label' => 'Paid'],
            ['key' => 'balance', 'label' => 'Balance'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'created_at', 'label' => 'Date'],
        ];

        $debtsQuery = Debt::with('customer', 'sale')
            ->when($this->search, fn ($q) => $q->whereHas('customer', fn ($c) => $c->where('name', 'like', "%{$this->search}%")));

        if ($this->statusFilter === 'outstanding') {
            $debtsQuery->whereIn('status', ['unpaid', 'partial']);
        } elseif ($this->statusFilter !== 'all') {
            $debtsQuery->where('status', $this->statusFilter);
        }

        $debts = $debtsQuery->latest()->paginate(20);

        $totalOutstanding = Debt::whereIn('status', ['unpaid', 'partial'])
            ->selectRaw('SUM(amount_owed - amount_paid) as total')
            ->value('total') ?? 0;

        $totalCollectedToday = DebtPayment::whereDate('created_at', today())->sum('amount');

        $totalDebtors = Debt::whereIn('status', ['unpaid', 'partial'])
            ->distinct('customer_id')
            ->count('customer_id');

        $totalPaidDebts = Debt::where('status', 'paid')->count();

        $viewDebt = $this->viewDebtId
            ? Debt::with('customer', 'sale.saleItems.product', 'payments.receiver')->find($this->viewDebtId)
            : null;

        return view('livewire.debt-book.index', [
            'headers' => $headers,
            'debts' => $debts,
            'totalOutstanding' => $totalOutstanding,
            'totalCollectedToday' => $totalCollectedToday,
            'totalDebtors' => $totalDebtors,
            'totalPaidDebts' => $totalPaidDebts,
            'viewDebt' => $viewDebt,
        ]);
    }
}
