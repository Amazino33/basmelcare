<div>
    <x-header title="Sales History" subtitle="View all completed sales" />

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
            <x-badge :value="ucfirst($sale->payment_method)" class="badge-ghost" />
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
                @foreach($viewSale->saleItems as $item)
                    <div class="flex justify-between items-center p-2 bg-base-200 rounded">
                        <div>
                            <div class="font-semibold text-sm">{{ $item->product->name }}</div>
                            <div class="text-xs text-base-content/60">Batch: {{ $item->batch->batch_number }} | Qty: {{ $item->quantity }} × ₦{{ number_format($item->unit_price, 2) }}</div>
                        </div>
                        <div class="font-bold">₦{{ number_format($item->subtotal, 2) }}</div>
                    </div>
                @endforeach
            </div>

            <x-hr />

            <div class="flex justify-between text-lg font-bold">
                <span>Total</span>
                <span class="text-primary">₦{{ number_format($viewSale->total_amount, 2) }}</span>
            </div>

            @if($viewSale->note)
                <div class="mt-3 text-sm text-base-content/60">Note: {{ $viewSale->note }}</div>
            @endif
        @endif
    </x-drawer>
</div>
