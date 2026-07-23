<div>
    {{-- Header with title and create button --}}
    <x-header title="Categories" subtitle="Manage product categories">
        <x-slot:actions>
            <x-button label="Create" wire:click="create" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    {{-- Table --}}
    <x-table :headers="$headers" :rows="$categories">
        @scope('actions', $category)
            <div class="flex gap-1">
                <x-button icon="o-pencil" wire:click="edit({{ $category->id }})" class="btn-xs btn-ghost" tooltip="Edit" />
                <x-button icon="o-trash" wire:click="delete({{ $category->id }})" class="btn-xs btn-ghost text-error" wire:confirm="Delete this category?" tooltip="Delete" />
            </div>
        @endscope
    </x-table>

    {{-- Create/Edit Modal --}}
    <x-modal wire:model="modal" title="{{ $categoryId ? 'Edit Category' : 'Create Category' }}">
        <x-form wire:submit="save">
            <x-input label="Name" wire:model="name" />
            <x-textarea label="Description" wire:model="description" />
            <x-slot:actions>
                <x-button :label="$categoryId ? 'Cancel' : 'Done'" @click="$wire.modal = false" />
                <x-button label="Save" type="submit" class="btn-primary" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>

@script
<script>
    $wire.on('focus-category-name', () => {
        setTimeout(() => {
            const el = document.querySelector('[wire\\:model="name"]');
            if (el) el.focus();
        }, 150);
    });
</script>
@endscript
