<div>
    <x-header title="Stock Adjustments" subtitle="Add or remove stock with reasons">
        <x-slot:actions>
            <x-button label="New Adjustment" wire:click="openAdjustment" icon="o-adjustments-horizontal" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-table :headers="$headers" :rows="$adjustments" with-pagination>
        @scope('cell_created_at', $movement)
            {{ $movement->created_at->format('M d, Y H:i') }}
        @endscope

        @scope('cell_quantity', $movement)
            <span @class(['font-bold', 'text-success' => $movement->quantity > 0, 'text-error' => $movement->quantity < 0])>
                {{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}
            </span>
        @endscope
    </x-table>

    <x-modal wire:model="modal" title="Stock Adjustment" box-class="max-w-lg">
        <x-form wire:submit="adjust">
            <x-select label="Product" wire:model.live="product_id" :options="$products" option-value="id" option-label="name" placeholder="Select product" />

            @if($product_id)
                <x-select label="Batch" wire:model="batch_id" :options="$batches" option-value="id" option-label="name" placeholder="Select batch" />
            @endif

            @if($batch_id)
                <x-radio label="Adjustment Type" wire:model="adjustment_type" :options="[
                    ['id' => 'add', 'name' => 'Add Stock (received, returned)'],
                    ['id' => 'remove', 'name' => 'Remove Stock (damaged, lost)'],
                ]" option-value="id" option-label="name" />

                <x-select label="Reason" wire:model="reason" :options="$reasons" option-value="id" option-label="name" placeholder="Select reason" />
                <x-input label="Quantity" wire:model="adjust_qty" type="number" min="1" />
                <x-textarea label="Note" wire:model="adjust_note" placeholder="Additional details" rows="2" />
            @endif

            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.modal = false" />
                @if($batch_id)
                    <x-button label="Apply Adjustment" type="submit" class="btn-primary" />
                @endif
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
