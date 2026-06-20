<div>
    <x-header title="Locations" subtitle="Manage pharmacy locations">
        <x-slot:actions>
            <x-button label="Add Location" wire:click="create" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-table :headers="$headers" :rows="$locations">
        @scope('cell_type', $location)
            <x-badge :value="ucfirst($location->type)" @class([
                'badge-primary' => $location->type === 'shop',
                'badge-info' => $location->type === 'warehouse',
                'badge-ghost' => $location->type === 'storage',
            ]) />
        @endscope

        @scope('cell_stock_count', $location)
            {{ number_format($location->stock_count) }} units
        @endscope

        @scope('cell_is_default', $location)
            @if($location->is_default)
                <x-badge value="Default" class="badge-success" />
            @endif
        @endscope

        @scope('actions', $location)
            <div class="flex gap-1">
                <x-button icon="o-pencil" wire:click="edit({{ $location->id }})" class="btn-xs btn-ghost" tooltip="Edit" />
                <x-button icon="o-trash" wire:click="delete({{ $location->id }})" class="btn-xs btn-ghost text-error" wire:confirm="Delete this location?" tooltip="Delete" />
            </div>
        @endscope
    </x-table>

    <x-modal wire:model="modal" title="{{ $locationId ? 'Edit Location' : 'New Location' }}">
        <x-form wire:submit="save">
            <x-input label="Location Name" wire:model="name" placeholder="e.g. Main Shop, Warehouse" />
            <x-select label="Type" wire:model="type" :options="[
                ['id' => 'shop', 'name' => 'Shop'],
                ['id' => 'warehouse', 'name' => 'Warehouse'],
                ['id' => 'storage', 'name' => 'Storage'],
            ]" option-value="id" option-label="name" />
            <x-input label="Address" wire:model="address" placeholder="Optional" />
            <x-toggle label="Set as default location" wire:model="is_default" hint="New stock will be assigned here by default" />
            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.modal = false" />
                <x-button label="Save" type="submit" class="btn-primary" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
