<div>
    <x-header title="Customers" subtitle="Manage customer records">
        <x-slot:middle class="!justify-end">
            <x-input icon="o-magnifying-glass" placeholder="Search..." wire:model.live.debounce="search" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Add Customer" wire:click="create" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-table :headers="$headers" :rows="$customers" with-pagination>
        @scope('actions', $customer)
            <x-button icon="o-pencil" wire:click="edit({{ $customer->id }})" class="btn-sm btn-ghost" />
            <x-button icon="o-trash" wire:click="delete({{ $customer->id }})" class="btn-sm btn-ghost text-error" wire:confirm="Delete this customer?" />
        @endscope
    </x-table>

    <x-modal wire:model="modal" title="{{ $customerId ? 'Edit Customer' : 'New Customer' }}">
        <x-form wire:submit="save">
            <x-input label="Name" wire:model="name" />
            <x-input label="Phone" wire:model="phone" />
            <x-input label="Email" wire:model="email" type="email" />
            <x-textarea label="Address" wire:model="address" rows="2" />
            <x-textarea label="Notes" wire:model="notes" rows="2" />
            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.modal = false" />
                <x-button label="Save" type="submit" class="btn-primary" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
