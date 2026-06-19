<div>
    <x-header title="Stock Levels" subtitle="Monitor inventory across all products">
        <x-slot:middle class="!justify-end">
            <x-input icon="o-magnifying-glass" placeholder="Search..." wire:model.live.debounce="search" clearable />
        </x-slot:middle>
    </x-header>

    <x-table :headers="$headers" :rows="$products" with-pagination>
        @scope('cell_selling_price', $product)
            ₦{{ number_format($product->selling_price, 2) }}
        @endscope

        @scope('cell_stock', $product)
            {{ $product->batches->sum('quantity') }}
        @endscope

        @scope('cell_status', $product)
            @php $stock = $product->batches->sum('quantity'); @endphp
            @if($stock == 0)
                <x-badge value="Out of Stock" class="badge-error" />
            @elseif($stock <= $product->reorder_level)
                <x-badge value="Low Stock" class="badge-warning" />
            @else
                <x-badge value="In Stock" class="badge-success" />
            @endif
        @endscope
    </x-table>
</div>
