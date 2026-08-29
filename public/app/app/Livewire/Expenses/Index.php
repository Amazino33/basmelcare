<?php

namespace App\Livewire\Expenses;

use App\Livewire\Concerns\DeniesAuditorWrites;

use App\Models\Expense;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use DeniesAuditorWrites;
    use Toast, WithPagination;

    public string $search = '';
    public string $categoryFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';

    /**
     * Who may record and correct an expense.
     *
     * The cashier is on this list because she is the one handing over the
     * money — transport, diesel, a repair — and the route has always put this
     * page on her path. She was left off by accident: the Record button did
     * nothing when she clicked it, and the fix at the time was to hide the
     * button rather than to make it work.
     */
    public bool $canManage = false;

    /**
     * Deleting is narrower than recording.
     *
     * An expense is the record that money left the till. Removing one removes
     * the evidence, so it stays with management; a cashier who typed the wrong
     * figure edits it, which leaves a trail.
     */
    public bool $canDelete = false;

    public bool $modal = false;
    public ?int $editId = null;
    public string $category = '';
    public string $description = '';
    public string $amount = '';
    public string $expense_date = '';

    public function mount(): void
    {
        $this->dateFrom = today()->startOfMonth()->toDateString();
        $this->dateTo = today()->toDateString();
        $this->expense_date = today()->toDateString();
        $roles = auth()->user()->role ?? [];

        $this->canManage = (bool) array_intersect($roles, ['admin', 'branch_manager', 'cashier']);
        $this->canDelete = (bool) array_intersect($roles, ['admin', 'branch_manager']);
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedCategoryFilter(): void { $this->resetPage(); }

    public function openCreate(): void
    {
        // openEdit already refused; this one opened the form to anybody and
        // only failed at save, which is a worse way to be told no.
        if (! $this->canManage) return;

        $this->reset(['editId', 'category', 'description', 'amount']);
        $this->expense_date = today()->toDateString();
        $this->modal = true;
    }

    public function openEdit(int $id): void
    {
        if (!$this->canManage) return;
        $expense = Expense::findOrFail($id);
        $this->editId = $id;
        $this->category = $expense->category;
        $this->description = $expense->description;
        $this->amount = (string) $expense->amount;
        $this->expense_date = $expense->expense_date->toDateString();
        $this->modal = true;
    }

    public function save(): void
    {
        if ($this->blockedAsAuditor()) return;

        // The open guards checked canManage and this one did not, so the rule
        // for reaching the form differed from the rule for saving it. Whoever
        // could call this directly was governed by a different list.
        if (! $this->canManage) return;

        $this->validate([
            'category'     => 'required|string',
            'description'  => 'required|string|max:500',
            'amount'       => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
        ]);

        $data = [
            'category'     => $this->category,
            'description'  => $this->description,
            'amount'       => $this->amount,
            'expense_date' => $this->expense_date,
        ];

        if ($this->editId) {
            Expense::findOrFail($this->editId)->update($data);
            $this->success('Expense updated.');
        } else {
            Expense::create(array_merge($data, ['user_id' => auth()->id()]));
            $this->success('Expense recorded.');
        }

        $this->modal = false;
    }

    public function delete(int $id): void
    {
        if ($this->blockedAsAuditor()) return;

        if (! $this->canDelete) return;
        Expense::findOrFail($id)->delete();
        $this->success('Expense deleted.');
    }

    public function render()
    {
        $query = Expense::with(['user', 'branch'])
            ->when($this->search, fn($q) => $q->where('description', 'like', "%{$this->search}%"))
            ->when($this->categoryFilter, fn($q) => $q->where('category', $this->categoryFilter))
            // whereDate rather than whereBetween: a bare upper bound excludes any
            // row stored with a time component, which is how SQLite keeps a
            // 'date' cast. MySQL happens not to, but the query should not
            // depend on which database it is running against.
            ->whereDate('expense_date', '>=', $this->dateFrom ?: '2000-01-01')
            ->whereDate('expense_date', '<=', $this->dateTo ?: today()->toDateString());

        $expenses = $query->clone()->latest('expense_date')->paginate(20);

        $totalToday = Expense::whereDate('expense_date', today())->sum('amount');

        $totalMonth = Expense::whereMonth('expense_date', today()->month)
            ->whereYear('expense_date', today()->year)
            ->sum('amount');

        $totalFiltered = Expense::when($this->categoryFilter, fn($q) => $q->where('category', $this->categoryFilter))
            ->whereBetween('expense_date', [$this->dateFrom ?: '2000-01-01', $this->dateTo ?: today()->toDateString()])
            ->sum('amount');

        $byCategory = Expense::selectRaw('category, SUM(amount) as total')
            ->whereBetween('expense_date', [$this->dateFrom ?: '2000-01-01', $this->dateTo ?: today()->toDateString()])
            ->groupBy('category')
            ->pluck('total', 'category');

        return view('livewire.expenses.index', [
            'expenses'      => $expenses,
            'totalToday'    => $totalToday,
            'totalMonth'    => $totalMonth,
            'totalFiltered' => $totalFiltered,
            'byCategory'    => $byCategory,
            'categories'    => Expense::categories(),
        ]);
    }
}
