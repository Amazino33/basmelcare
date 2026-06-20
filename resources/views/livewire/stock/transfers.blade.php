<div>
    <x-header title="Stock Transfers" subtitle="Move stock between locations">
        <x-slot:actions>
            <x-button label="New Transfer" wire:click="openTransfer" icon="o-arrows-right-left" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-table :headers="$headers" :rows="$transfers" with-pagination>
        @scope('cell_created_at', $movement)
            {{ $movement->created_at->format('M d, Y H:i') }}
        @endscope

        @scope('cell_quantity', $movement)
            <span class="font-bold">{{ abs($movement->quantity) }}</span>
        @endscope

        @scope('cell_from', $movement)
            {{ $movement->fromLocation?->name ?? '—' }}
        @endscope

        @scope('cell_to', $movement)
            {{ $movement->toLocation?->name ?? '—' }}
        @endscope
    </x-table>

    <x-modal wire:model="modal" title="Transfer Stock" box-class="max-w-lg">
        <x-form wire:submit="transfer">
            <x-select label="Product" wire:model.live="product_id" :options="$products" option-value="id" option-label="name" placeholder="Select product" />

            @if($product_id)
                <x-select label="Source Batch" wire:model.live="batch_id" :options="$batches" option-value="id" option-label="name" placeholder="Select batch" />
            @endif

            @if($batch_id)
                <x-select label="From Location" wire:model="from_location_id" :options="$locations" option-value="id" option-label="name" placeholder="Source" disabled />
                <x-select label="To Location" wire:model="to_location_id" :options="$locations" option-value="id" option-label="name" placeholder="Destination" />
                <x-input label="Quantity" wire:model="transfer_qty" type="number" min="1" />
                <x-textarea label="Note" wire:model="transfer_note" placeholder="Reason for transfer" rows="2" />
            @endif

            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.modal = false" />
                @if($batch_id)
                    <x-button label="Transfer" type="submit" class="btn-primary" icon="o-arrows-right-left" />
                @endif
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
