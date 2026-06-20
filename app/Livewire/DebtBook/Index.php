<?php

namespace App\Livewire\DebtBook;

use App\Models\Debt;
use App\Models\DebtPayment;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast, WithPagination;

    public string $search = '';
    public string $statusFilter = 'outstanding';

    // Payment form
    public ?int $payDebtId = null;
    public string $pay_amount = '';
    public string $pay_method = 'cash';
    public string $pay_note = '';
    public bool $payModal = false;

    // Details
    public ?int $viewDebtId = null;
    public bool $detailsDrawer = false;

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
        $this->validate([
            'pay_amount' => 'required|numeric|min:0.01',
            'pay_method' => 'required|in:cash,card,transfer',
            'pay_note' => 'nullable|string|max:500',
        ]);

        $debt = Debt::findOrFail($this->payDebtId);
        $amount = (float) $this->pay_amount;

        if ($amount > $debt->balance) {
            $this->error('Payment exceeds outstanding balance (₦' . number_format($debt->balance, 2) . ').');
            return;
        }

        DebtPayment::create([
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

        $this->payModal = false;
        $this->success('Payment of ₦' . number_format($amount, 2) . ' recorded.');
        $this->reset(['payDebtId', 'pay_amount', 'pay_method', 'pay_note']);
    }

    public function viewDetails($debtId)
    {
        $this->viewDebtId = $debtId;
        $this->detailsDrawer = true;
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
            ->when($this->search, fn($q) => $q->whereHas('customer', fn($c) => $c->where('name', 'like', "%{$this->search}%")));

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
