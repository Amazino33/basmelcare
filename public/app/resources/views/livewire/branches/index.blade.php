<div>
    <x-header title="Branches" subtitle="Manage pharmacy branches">
        <x-slot:actions>
            <x-button label="Add Branch" wire:click="create" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-table :headers="$headers" :rows="$branches">
        @scope('cell_staff_count', $branch)
            {{ $branch->staff_count }} staff
        @endscope

        @scope('cell_is_main', $branch)
            @if($branch->is_main)
                <x-badge value="Main" class="badge-success" />
            @endif
        @endscope

        @scope('actions', $branch)
            <div class="flex gap-1">
                <x-button icon="o-pencil" wire:click="edit({{ $branch->id }})" class="btn-xs btn-ghost" tooltip="Edit" />
                <x-button icon="o-trash" wire:click="delete({{ $branch->id }})" class="btn-xs btn-ghost text-error" wire:confirm="Delete this branch?" tooltip="Delete" />
            </div>
        @endscope
    </x-table>

    <x-modal wire:model="modal" title="{{ $branchId ? 'Edit Branch' : 'New Branch' }}">
        <x-form wire:submit="save">
            <x-input label="Branch Name" wire:model="name" placeholder="e.g. Main Branch, Ikeja Branch" />
            <x-input label="Phone" wire:model="phone" />
            <x-textarea label="Address" wire:model="address" rows="2" />
            <x-toggle label="Set as main branch" wire:model="is_main" />
            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.modal = false" />
                <x-button label="Save" type="submit" class="btn-primary" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
