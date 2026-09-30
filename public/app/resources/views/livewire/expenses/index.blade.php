<div>
    <x-header title="Expenses" subtitle="Track and manage operational costs">
        <x-slot:middle class="!justify-end">
            <x-input icon="o-magnifying-glass" placeholder="Search description..." wire:model.live.debounce="search" clearable />
        </x-slot:middle>
        <x-slot:actions>
            {{-- Shown only to those who can actually record one. A cashier or an
                 auditor clicking this got no modal and no message. --}}
            @if($canManage)
                <x-button label="Record Expense" icon="o-plus" wire:click="openCreate" class="btn-primary" />
            @endif
        </x-slot:actions>
    </x-header>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <x-stat title="Today" value="₦{{ number_format($totalToday, 2) }}" icon="o-calendar" color="text-warning" />
        <x-stat title="This Month" value="₦{{ number_format($totalMonth, 2) }}" icon="o-chart-bar" color="text-error" />
        <x-stat title="Cash Outflow" value="₦{{ number_format($totalCash, 2) }}" icon="o-banknotes" color="text-success" />
        <x-stat title="Transfer Outflow" value="₦{{ number_format($totalTransfer, 2) }}" icon="o-arrow-path-rounded-square" color="text-info" />
    </div>

    {{-- Category breakdown --}}
    @if($byCategory->isNotEmpty())
        <div class="flex flex-wrap gap-2 mb-4">
            @foreach($byCategory as $cat => $total)
                <div class="badge badge-outline gap-1 py-3 px-3">
                    <span class="font-semibold">{{ \App\Models\Expense::categories()[$cat] ?? ucfirst($cat) }}</span>
                    <span class="text-base-content/60">₦{{ number_format($total, 2) }}</span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <x-select wire:model.live="categoryFilter" :options="collect([['id' => '', 'name' => 'All Categories']])->merge(collect($categories)->map(fn($v, $k) => ['id' => $k, 'name' => $v])->values())" option-value="id" option-label="name" class="w-44" />
        <x-select wire:model.live="paymentMethodFilter" :options="[['id' => '', 'name' => 'All Paid From'], ['id' => 'cash', 'name' => 'Cash'], ['id' => 'transfer', 'name' => 'Transfer']]" option-value="id" option-label="name" class="w-40" />
        <x-input wire:model.live="dateFrom" type="date" class="w-36" />
        <x-input wire:model.live="dateTo" type="date" class="w-36" />
        @if($paymentMethodFilter || $categoryFilter)
            <div class="text-xs text-base-content/60 font-medium">
                Filtered Total: <span class="text-primary font-bold">₦{{ number_format($totalFiltered, 2) }}</span>
            </div>
        @endif
    </div>

    {{-- Table --}}
    <div class="rounded-xl border border-base-300 bg-base-100 shadow-xs overflow-hidden">
        <x-table :headers="[
            ['key' => 'expense_date',   'label' => 'Date'],
            ['key' => 'category',       'label' => 'Category'],
            ['key' => 'description',    'label' => 'Description'],
            ['key' => 'payment_method', 'label' => 'Paid From'],
            ['key' => 'amount',         'label' => 'Amount'],
            ['key' => 'logged_by',      'label' => 'Logged By'],
        ]" :rows="$expenses" with-pagination>

            @scope('cell_expense_date', $expense)
                {{ $expense->expense_date->format('M d, Y') }}
            @endscope

            @scope('cell_category', $expense)
                <x-badge :value="$categories[$expense->category] ?? ucfirst($expense->category)" class="badge-ghost" />
            @endscope

            @scope('cell_description', $expense)
                <span class="text-sm">{{ $expense->description }}</span>
            @endscope

            @scope('cell_payment_method', $expense)
                @if(($expense->payment_method ?? 'cash') === 'transfer')
                    <x-badge value="Transfer" class="badge-info badge-outline gap-1 font-medium" />
                @else
                    <x-badge value="Cash" class="badge-success badge-outline gap-1 font-medium" />
                @endif
            @endscope

            @scope('cell_amount', $expense)
                <span class="font-semibold text-error">₦{{ number_format($expense->amount, 2) }}</span>
            @endscope

            @scope('cell_logged_by', $expense)
                <div class="text-sm">{{ $expense->user->name }}</div>
                @if($expense->branch)
                    <div class="text-xs text-base-content/50">{{ $expense->branch->name }}</div>
                @endif
            @endscope

            @scope('actions', $expense)
                {{-- Correcting and deleting are different powers. A cashier who
                     typed the wrong figure fixes it; removing the record that money
                     left the till stays with management. --}}
                <div class="flex gap-1">
                    @if(auth()->user()->hasRole('admin') || auth()->user()->hasRole('branch_manager') || auth()->user()->hasRole('cashier'))
                        <x-button icon="o-pencil" wire:click="openEdit({{ $expense->id }})" class="btn-xs btn-ghost" tooltip="Edit" />
                    @endif

                    @if(auth()->user()->hasRole('admin') || auth()->user()->hasRole('branch_manager'))
                        <x-button icon="o-trash" wire:click="delete({{ $expense->id }})" wire:confirm="Delete this expense?" class="btn-xs btn-ghost text-error" tooltip="Delete" />
                    @endif
                </div>
            @endscope
        </x-table>
    </div>

    {{-- Create / Edit Modal --}}
    <x-modal wire:model="modal" :title="$editId ? 'Edit Expense' : 'Record Expense'">
        <x-form wire:submit="save">
            <x-select label="Category" wire:model="category" :options="collect($categories)->map(fn($v, $k) => ['id' => $k, 'name' => $v])->values()->toArray()" option-value="id" option-label="name" placeholder="Select category" />
            <x-textarea label="Description" wire:model="description" placeholder="What was this expense for?" rows="3" />

            {{-- Paid From (Cash vs Transfer) --}}
            <div class="form-control">
                <label class="label pb-1.5"><span class="label-text font-medium">Paid From <span class="text-error">*</span></span></label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors {{ $payment_method === 'cash' ? 'border-primary bg-primary/10 text-primary font-medium' : 'border-base-300 hover:border-base-400' }}">
                        <input type="radio" wire:model.live="payment_method" value="cash" class="radio radio-primary radio-sm" />
                        <div>
                            <div class="font-semibold text-sm">Cash</div>
                            <div class="text-xs text-base-content/60">From cash drawer / till</div>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors {{ $payment_method === 'transfer' ? 'border-primary bg-primary/10 text-primary font-medium' : 'border-base-300 hover:border-base-400' }}">
                        <input type="radio" wire:model.live="payment_method" value="transfer" class="radio radio-primary radio-sm" />
                        <div>
                            <div class="font-semibold text-sm">Transfer</div>
                            <div class="text-xs text-base-content/60">From bank account</div>
                        </div>
                    </label>
                </div>
                @error('payment_method') <span class="text-xs text-error mt-1">{{ $message }}</span> @enderror
            </div>

            <x-input label="Amount" wire:model="amount" prefix="₦" type="number" step="0.01" min="0.01" />
            <x-input label="Date" wire:model="expense_date" type="date" />
            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.modal = false" />
                <x-button label="{{ $editId ? 'Update' : 'Record' }}" type="submit" class="btn-primary" icon="o-check" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
