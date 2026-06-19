<div>
    <x-header title="Products" subtitle="Manage products and batches">
        <x-slot:middle class="!justify-end">
            <x-input icon="o-magnifying-glass" placeholder="Search..." wire:model.live.debounce="search" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Add Product" wire:click="createProduct" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-table :headers="$headers" :rows="$products" with-pagination>
        @scope('cell_selling_price', $product)
            ₦{{ number_format($product->selling_price, 2) }}
            @if($product->wholesale_price)
                <div class="text-xs text-info">W/S: ₦{{ number_format($product->wholesale_price, 2) }}{{ $product->wholesale_min_qty ? ' ('.$product->wholesale_min_qty.'+)' : '' }}</div>
            @endif
        @endscope

        @scope('cell_stock', $product)
            @php $total = $product->batches->sum('quantity'); @endphp
            <x-badge :value="$total" @class([
                'badge-success' => $total > $product->reorder_level,
                'badge-warning' => $total > 0 && $total <= $product->reorder_level,
                'badge-error' => $total == 0,
            ]) />
        @endscope

        @scope('actions', $product)
            <div class="flex gap-1">
                <x-button icon="o-eye" wire:click="viewBatches({{ $product->id }})" class="btn-sm btn-ghost" tooltip="View Batches" />
                <x-button icon="o-plus-circle" wire:click="openBatchModal({{ $product->id }})" class="btn-sm btn-ghost text-success" tooltip="Add Batch" />
                <x-button icon="o-pencil" wire:click="editProduct({{ $product->id }})" class="btn-sm btn-ghost" />
                <x-button icon="o-trash" wire:click="deleteProduct({{ $product->id }})" class="btn-sm btn-ghost text-error" wire:confirm="Delete this product and all its batches?" />
            </div>
        @endscope
    </x-table>

    <!-- Product Modal -->
    <x-modal wire:model="productModal" title="{{ $productId ? 'Edit Product' : 'New Product' }}">
        <x-form wire:submit="saveProduct">
            <x-input label="Product Name" wire:model="name" />
            <x-input label="SKU" wire:model="sku" placeholder="Optional" />
            <x-select label="Category" wire:model="category_id" :options="$categories" option-value="id" option-label="name" placeholder="Select category" />
            <x-input label="Selling Price (Retail)" wire:model="selling_price" prefix="₦" type="number" step="0.01" />
            <x-input label="Wholesale Price" wire:model="wholesale_price" prefix="₦" type="number" step="0.01" hint="Leave empty if no wholesale pricing" />
            <x-input label="Wholesale Min Qty" wire:model="wholesale_min_qty" type="number" hint="Retail buyers get wholesale price at this quantity" />
            <x-input label="Reorder Level" wire:model="reorder_level" type="number" hint="Alert when stock falls below this" />
            <x-textarea label="Description" wire:model="description" placeholder="Optional" />
            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.productModal = false" />
                <x-button label="Save" type="submit" class="btn-primary" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <!-- Batch Modal -->
    <x-modal wire:model="batchModal" title="Add Batch">
        <x-form wire:submit="saveBatch">
            <x-input label="Batch Number" wire:model="batch_number" placeholder="e.g. BN-2026-001" />
            <x-input label="Expiry Date" wire:model="expiry_date" type="date" />
            <x-input label="Cost Price" wire:model="cost_price" prefix="₦" type="number" step="0.01" />
            <x-input label="Quantity" wire:model="quantity" type="number" />
            <x-textarea label="Note" wire:model="batch_note" placeholder="Optional" />
            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.batchModal = false" />
                <x-button label="Add Batch" type="submit" class="btn-primary" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <!-- Batches Drawer -->
    <x-drawer wire:model="batchesDrawer" title="{{ $viewProduct?->name }} - Batches" right class="w-96 lg:w-1/3">
        @if($viewProduct && $viewProduct->batches->count())
            <div class="space-y-3">
                @foreach($viewProduct->batches as $batch)
                    <x-card>
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="font-semibold">{{ $batch->batch_number }}</div>
                                <div class="text-sm text-base-content/60">Cost: ₦{{ number_format($batch->cost_price, 2) }}</div>
                                <div class="text-sm text-base-content/60">Qty: {{ $batch->quantity }}</div>
                            </div>
                            <div class="text-right">
                                <x-badge :value="$batch->expiry_date->format('M d, Y')" @class([
                                    'badge-error' => $batch->expiry_date->isPast(),
                                    'badge-warning' => $batch->expiry_date->isBetween(now(), now()->addDays(90)),
                                    'badge-success' => $batch->expiry_date->isAfter(now()->addDays(90)),
                                ]) />
                            </div>
                        </div>
                    </x-card>
                @endforeach
            </div>
        @else
            <div class="text-center py-8 text-base-content/60">No batches yet.</div>
        @endif

        <x-slot:actions>
            <x-button label="Add Batch" wire:click="openBatchModal({{ $viewBatchesProductId }})" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-drawer>
</div>
