<div>
    <x-header title="Movement History" subtitle="Full audit log of all stock movements">
        <x-slot:middle class="!justify-end">
            <x-input icon="o-magnifying-glass" placeholder="Search product..." wire:model.live.debounce="search" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-select wire:model.live="typeFilter" :options="$typeOptions" option-value="id" option-label="name" class="w-40" />
        </x-slot:actions>
    </x-header>

    <x-table :headers="$headers" :rows="$movements" with-pagination>
        @scope('cell_created_at', $movement)
            {{ $movement->created_at->format('M d, Y H:i') }}
        @endscope

        @scope('cell_type', $movement)
            @php
                $colors = [
                    'purchase' => 'badge-success',
                    'sale' => 'badge-primary',
                    'adjustment' => 'badge-warning',
                    'transfer_in' => 'badge-info',
                    'transfer_out' => 'badge-info',
                    'return' => 'badge-ghost',
                ];
            @endphp
            <x-badge :value="ucfirst(str_replace('_', ' ', $movement->type))" :class="$colors[$movement->type] ?? 'badge-ghost'" />
        @endscope

        @scope('cell_quantity', $movement)
            <span @class(['font-bold', 'text-success' => $movement->quantity > 0, 'text-error' => $movement->quantity < 0])>
                {{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}
            </span>
        @endscope

        @scope('cell_from', $movement)
            {{ $movement->fromLocation?->name ?? '—' }}
        @endscope

        @scope('cell_to', $movement)
            {{ $movement->toLocation?->name ?? '—' }}
        @endscope

        @scope('cell_user.name', $movement)
            {{ $movement->user?->name ?? '—' }}
        @endscope
    </x-table>
</div>
