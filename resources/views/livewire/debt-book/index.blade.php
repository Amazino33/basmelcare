<div>
    <x-header title="Debt Book" subtitle="Track credit sales and customer payments">
        <x-slot:middle class="!justify-end">
            <x-input icon="o-magnifying-glass" placeholder="Search customer..." wire:model.live.debounce="search" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-select wire:model.live="statusFilter" :options="[
                ['id' => 'outstanding', 'name' => 'Outstanding'],
                ['id' => 'unpaid', 'name' => 'Unpaid'],
                ['id' => 'partial', 'name' => 'Partial'],
                ['id' => 'paid', 'name' => 'Fully Paid'],
                ['id' => 'all', 'name' => 'All'],
            ]" option-value="id" option-label="name" class="w-36" />
        </x-slot:actions>
    </x-header>

    <!-- Summary Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <x-stat
            title="Total Outstanding"
            value="₦{{ number_format($totalOutstanding, 2) }}"
            description="{{ $totalDebtors }} debtor(s)"
            icon="o-banknotes"
            color="text-error"
        />
        <x-stat
            title="Collected Today"
            value="₦{{ number_format($totalCollectedToday, 2) }}"
            description="Payments received"
            icon="o-arrow-down-tray"
            color="text-success"
        />
        <x-stat
            title="Active Debtors"
            value="{{ $totalDebtors }}"
            description="Customers owing"
            icon="o-users"
            color="text-warning"
        />
        <x-stat
            title="Cleared Debts"
            value="{{ $totalPaidDebts }}"
            description="Fully paid"
            icon="o-check-circle"
            color="text-success"
        />
    </div>

    <!-- Debts Table -->
    <x-table :headers="$headers" :rows="$debts" with-pagination>
        @scope('cell_sale_id', $debt)
            #{{ $debt->sale_id }}
        @endscope

        @scope('cell_amount_owed', $debt)
            ₦{{ number_format($debt->amount_owed, 2) }}
        @endscope

        @scope('cell_amount_paid', $debt)
            ₦{{ number_format($debt->amount_paid, 2) }}
        @endscope

        @scope('cell_balance', $debt)
            <span class="font-bold {{ $debt->balance > 0 ? 'text-error' : 'text-success' }}">
                ₦{{ number_format($debt->balance, 2) }}
            </span>
        @endscope

        @scope('cell_status', $debt)
            <x-badge :value="ucfirst($debt->status)" @class([
                'badge-error' => $debt->status === 'unpaid',
                'badge-warning' => $debt->status === 'partial',
                'badge-success' => $debt->status === 'paid',
            ]) />
        @endscope

        @scope('cell_created_at', $debt)
            {{ $debt->created_at->format('M d, Y') }}
        @endscope

        @scope('actions', $debt)
            <div class="flex gap-1">
                <x-button icon="o-eye" wire:click="viewDetails({{ $debt->id }})" class="btn-xs btn-ghost" tooltip="Details" />
                @if($debt->status !== 'paid')
                    <x-button icon="o-banknotes" wire:click="openPayment({{ $debt->id }})" class="btn-xs btn-ghost text-success" tooltip="Record Payment" />
                @endif
            </div>
        @endscope
    </x-table>

    <!-- Record Payment Modal -->
    <x-modal wire:model="payModal" title="Record Payment">
        <x-form wire:submit="recordPayment">
            @if($payDebtId)
                @php $payDebt = \App\Models\Debt::with('customer')->find($payDebtId); @endphp
                @if($payDebt)
                    <div class="bg-base-200 rounded-lg p-3 mb-4">
                        <div class="flex justify-between text-sm"><span class="text-base-content/60">Customer:</span> <span class="font-semibold">{{ $payDebt->customer->name }}</span></div>
                        <div class="flex justify-between text-sm"><span class="text-base-content/60">Total Owed:</span> <span>₦{{ number_format($payDebt->amount_owed, 2) }}</span></div>
                        <div class="flex justify-between text-sm"><span class="text-base-content/60">Already Paid:</span> <span>₦{{ number_format($payDebt->amount_paid, 2) }}</span></div>
                        <div class="flex justify-between text-sm font-bold mt-1 pt-1 border-t border-base-300"><span>Balance:</span> <span class="text-error">₦{{ number_format($payDebt->balance, 2) }}</span></div>
                    </div>
                @endif
            @endif

            <x-input label="Payment Amount" wire:model="pay_amount" prefix="₦" type="number" step="0.01" />
            <x-select label="Payment Method" wire:model="pay_method" :options="[
                ['id' => 'cash', 'name' => 'Cash'],
                ['id' => 'card', 'name' => 'Card'],
                ['id' => 'transfer', 'name' => 'Transfer'],
            ]" option-value="id" option-label="name" />
            <x-textarea label="Note" wire:model="pay_note" placeholder="Optional" rows="2" />
            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.payModal = false" />
                <x-button label="Record Payment" type="submit" class="btn-primary" icon="o-check" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    @script
    <script>
        $wire.on('open-debt-receipt', ({ id }) => {
            window.open(`/desk/debt-payment/${id}/receipt`, '_blank');
        });
    </script>
    @endscript

    <!-- Debt Details Drawer -->
    <x-drawer wire:model="detailsDrawer" title="Debt Details" right class="w-96 lg:w-1/3">
        @if($viewDebt)
            <div class="space-y-2 mb-4">
                <div class="flex justify-between"><span class="text-base-content/60">Customer:</span> <span class="font-semibold">{{ $viewDebt->customer->name }}</span></div>
                <div class="flex justify-between"><span class="text-base-content/60">Sale #:</span> <span>{{ $viewDebt->sale_id }}</span></div>
                <div class="flex justify-between"><span class="text-base-content/60">Date:</span> <span>{{ $viewDebt->created_at->format('M d, Y H:i') }}</span></div>
                <div class="flex justify-between"><span class="text-base-content/60">Status:</span>
                    <x-badge :value="ucfirst($viewDebt->status)" @class([
                        'badge-error' => $viewDebt->status === 'unpaid',
                        'badge-warning' => $viewDebt->status === 'partial',
                        'badge-success' => $viewDebt->status === 'paid',
                    ]) />
                </div>
            </div>

            <div class="bg-base-200 rounded-lg p-3 mb-4">
                <div class="flex justify-between text-sm"><span>Owed:</span> <span>₦{{ number_format($viewDebt->amount_owed, 2) }}</span></div>
                <div class="flex justify-between text-sm"><span>Paid:</span> <span class="text-success">₦{{ number_format($viewDebt->amount_paid, 2) }}</span></div>
                <div class="flex justify-between font-bold mt-1 pt-1 border-t border-base-300"><span>Balance:</span> <span class="text-error">₦{{ number_format($viewDebt->balance, 2) }}</span></div>
            </div>

            <!-- Items purchased -->
            <div class="text-sm font-semibold text-base-content/60 uppercase mb-2">Items Purchased</div>
            <div class="space-y-1 mb-4">
                @foreach($viewDebt->sale->saleItems as $item)
                    <div class="flex justify-between text-sm p-1">
                        <span>{{ $item->product->name }} × {{ $item->quantity }}</span>
                        <span>₦{{ number_format($item->subtotal, 2) }}</span>
                    </div>
                @endforeach
            </div>

            <x-hr />

            <!-- Payment history -->
            <div class="text-sm font-semibold text-base-content/60 uppercase mb-2">Payment History</div>
            @forelse($viewDebt->payments as $payment)
                <div class="flex justify-between items-center p-2 bg-base-200 rounded mb-2">
                    <div>
                        <div class="font-semibold text-sm text-success">₦{{ number_format($payment->amount, 2) }}</div>
                        <div class="text-xs text-base-content/60">{{ ucfirst($payment->payment_method) }} | {{ $payment->receiver->name }}</div>
                        <div class="text-xs text-base-content/60">{{ $payment->created_at->format('M d, Y H:i') }}</div>
                        @if($payment->note)
                            <div class="text-xs italic mt-1">{{ $payment->note }}</div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-center py-4 text-base-content/60">No payments recorded.</div>
            @endforelse

            @if($viewDebt->status !== 'paid')
                <div class="mt-4">
                    <x-button label="Record Payment" wire:click="openPayment({{ $viewDebt->id }})" class="btn-primary btn-block" icon="o-banknotes" />
                </div>
            @endif
        @endif
    </x-drawer>
</div>
