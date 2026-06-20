<div>
    <x-header title="Sales History" subtitle="View all sales and performance">
        <x-slot:middle class="!justify-end">
            <x-input icon="o-magnifying-glass" placeholder="Search by ID, customer, cashier..." wire:model.live.debounce="search" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-select wire:model.live="period" :options="[
                ['id' => 'today', 'name' => 'Today'],
                ['id' => 'week', 'name' => 'This Week'],
                ['id' => 'month', 'name' => 'This Month'],
                ['id' => 'year', 'name' => 'This Year'],
                ['id' => 'all', 'name' => 'All Time'],
            ]" option-value="id" option-label="name" class="w-36" />
        </x-slot:actions>
    </x-header>

    <!-- Summary Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <x-stat
            title="Revenue"
            value="₦{{ number_format($totalRevenue, 2) }}"
            description="{{ $totalTransactions }} sales"
            icon="o-banknotes"
            color="text-primary"
        />
        <x-stat
            title="Profit"
            value="₦{{ number_format($totalProfit, 2) }}"
            description="{{ $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 1) : 0 }}% margin"
            icon="o-arrow-trending-up"
            color="{{ $totalProfit >= 0 ? 'text-success' : 'text-error' }}"
        />
        <x-stat
            title="Items Sold"
            value="{{ number_format($totalItemsSold) }}"
            description="Avg ₦{{ number_format($avgSale, 2) }}/sale"
            icon="o-shopping-bag"
            color="text-info"
        />
        <div class="bg-base-100 rounded-lg p-4 shadow-sm">
            <div class="text-sm text-base-content/60 mb-2">Payment Methods</div>
            @forelse($paymentBreakdown as $method)
                <div class="flex justify-between items-center text-sm mb-1">
                    <span class="flex items-center gap-2">
                        <x-badge :value="ucfirst($method->payment_method)" @class([
                            'badge-xs',
                            'badge-success' => $method->payment_method === 'cash',
                            'badge-info' => $method->payment_method === 'transfer',
                            'badge-primary' => $method->payment_method === 'card',
                        ]) />
                        <span class="text-base-content/60">{{ $method->count }}×</span>
                    </span>
                    <span class="font-semibold">₦{{ number_format($method->total, 2) }}</span>
                </div>
            @empty
                <div class="text-sm text-base-content/40">No sales yet</div>
            @endforelse
        </div>
    </div>

    <!-- Sales Table -->
    <x-table :headers="$headers" :rows="$sales" with-pagination>
        @scope('cell_created_at', $sale)
            {{ $sale->created_at->format('M d, Y H:i') }}
        @endscope

        @scope('cell_customer.name', $sale)
            {{ $sale->customer?->name ?? 'Walk-in' }}
        @endscope

        @scope('cell_total_amount', $sale)
            ₦{{ number_format($sale->total_amount, 2) }}
        @endscope

        @scope('cell_payment_method', $sale)
            <x-badge :value="ucfirst($sale->payment_method)" @class([
                'badge-success' => $sale->payment_method === 'cash',
                'badge-info' => $sale->payment_method === 'transfer',
                'badge-primary' => $sale->payment_method === 'card',
            ]) />
        @endscope

        @scope('cell_status', $sale)
            <x-badge :value="ucfirst($sale->status)" @class([
                'badge-success' => $sale->status === 'completed',
                'badge-warning' => $sale->status === 'pending',
                'badge-error' => $sale->status === 'cancelled',
            ]) />
        @endscope

        @scope('actions', $sale)
            <x-button icon="o-eye" wire:click="viewDetails({{ $sale->id }})" class="btn-sm btn-ghost" />
            <x-button icon="o-printer" link="{{ route('invoice.show', $sale->id) }}" class="btn-sm btn-ghost" tooltip="Invoice" external />
        @endscope
    </x-table>

    <!-- Sale Details Drawer -->
    <x-drawer wire:model="detailsDrawer" title="Sale #{{ $viewSale?->id }}" right class="w-96 lg:w-1/3">
        @if($viewSale)
            <div class="space-y-2 mb-4">
                <div class="flex justify-between"><span class="text-base-content/60">Date:</span> <span>{{ $viewSale->created_at->format('M d, Y H:i') }}</span></div>
                <div class="flex justify-between"><span class="text-base-content/60">Cashier:</span> <span>{{ $viewSale->user->name }}</span></div>
                <div class="flex justify-between"><span class="text-base-content/60">Customer:</span> <span>{{ $viewSale->customer?->name ?? 'Walk-in' }}</span></div>
                <div class="flex justify-between"><span class="text-base-content/60">Payment:</span> <span>{{ ucfirst($viewSale->payment_method) }}</span></div>
            </div>

            <x-hr />

            <div class="space-y-2">
                @php $saleProfit = 0; @endphp
                @foreach($viewSale->saleItems as $item)
                    @php $saleProfit += ($item->unit_price - $item->cost_price) * $item->quantity; @endphp
                    <div class="flex justify-between items-center p-2 bg-base-200 rounded">
                        <div>
                            <div class="font-semibold text-sm">{{ $item->product->name }}</div>
                            <div class="text-xs text-base-content/60">Batch: {{ $item->batch->batch_number }} | Qty: {{ $item->quantity }} × ₦{{ number_format($item->unit_price, 2) }}</div>
                            <div class="text-xs text-success">Profit: ₦{{ number_format(($item->unit_price - $item->cost_price) * $item->quantity, 2) }}</div>
                        </div>
                        <div class="font-bold">₦{{ number_format($item->subtotal, 2) }}</div>
                    </div>
                @endforeach
            </div>

            <x-hr />

            <div class="space-y-1">
                <div class="flex justify-between text-lg font-bold">
                    <span>Total</span>
                    <span class="text-primary">₦{{ number_format($viewSale->total_amount, 2) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-base-content/60">Profit</span>
                    <span class="text-success font-semibold">₦{{ number_format($saleProfit, 2) }}</span>
                </div>
            </div>

            @if($viewSale->note)
                <div class="mt-3 text-sm text-base-content/60">Note: {{ $viewSale->note }}</div>
            @endif

            <div class="mt-4">
                <x-button label="Print Invoice" link="{{ route('invoice.show', $viewSale->id) }}" class="btn-primary btn-block" icon="o-printer" external />
            </div>
        @endif
    </x-drawer>
</div>
