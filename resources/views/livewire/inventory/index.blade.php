<div>
    <x-header title="Stock Levels" subtitle="Monitor inventory across all products">
        <x-slot:middle class="!justify-end">
            <x-input icon="o-magnifying-glass" placeholder="Search..." wire:model.live.debounce="search" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <a href="{{ route('inventory.print') }}"
               onclick="window.open(this.href); return false;"
               class="btn btn-outline btn-sm gap-2">
                <x-icon name="o-printer" class="w-4 h-4" /> Print Stock Sheet
            </a>
        </x-slot:actions>
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
