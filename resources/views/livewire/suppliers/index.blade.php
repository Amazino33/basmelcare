<div>
    <x-header title="Suppliers" subtitle="Manage supplier records">
        <x-slot:middle class="!justify-end">
            <x-input icon="o-magnifying-glass" placeholder="Search..." wire:model.live.debounce="search" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Add Supplier" wire:click="create" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-table :headers="$headers" :rows="$suppliers" with-pagination>
        @scope('actions', $supplier)
            <x-button icon="o-pencil" wire:click="edit({{ $supplier->id }})" class="btn-sm btn-ghost" />
            <x-button icon="o-trash" wire:click="delete({{ $supplier->id }})" class="btn-sm btn-ghost text-error" wire:confirm="Delete this supplier?" />
        @endscope
    </x-table>

    <x-modal wire:model="modal" title="{{ $supplierId ? 'Edit Supplier' : 'New Supplier' }}">
        <x-form wire:submit="save">
            <x-input label="Company Name" wire:model="name" />
            <x-input label="Contact Person" wire:model="contact_person" />
            <x-input label="Phone" wire:model="phone" />
            <x-input label="Email" wire:model="email" type="email" />
            <x-textarea label="Address" wire:model="address" rows="2" />
            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.modal = false" />
                <x-button label="Save" type="submit" class="btn-primary" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
