<div>
    <x-header title="Movement History" subtitle="Full audit log of all stock movements">
        <x-slot:middle class="!justify-end">
            <x-input icon="o-magnifying-glass" placeholder="Search product..." wire:model.live.debounce="search" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-select wire:model.live="typeFilter" :options="$typeOptions" option-value="id" option-label="name" class="w-40" />
        </x-slot:actions>
    </x-header>

    <p class="text-sm text-base-content/60 mb-3">
        Tap any row to see what the batch held before and after that movement.
    </p>

    <x-table :headers="$headers" :rows="$movements" with-pagination
             @row-click="$wire.viewMovement($event.detail.id)">
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

    {{-- What the shelf actually held, either side of this movement. The log
         could say a sale took one and a return put one back, but not what the
         count was - so "the stock did not change" had no answer in it. --}}
    <x-drawer wire:model="detailDrawer" title="Stock movement" right class="w-96 lg:w-1/3">
        @if($viewMovement)
            @php
                $before = $viewMovement->balanceBefore();
                $after  = $viewMovement->balance_after;
            @endphp

            <div class="space-y-4">
                <div>
                    <div class="font-bold text-lg">{{ $viewMovement->batch?->product?->name ?? 'Product removed' }}</div>
                    <div class="text-sm text-base-content/60 font-mono">
                        {{ $viewMovement->batch?->batch_number ?? '—' }}
                    </div>
                </div>

                @if($after !== null)
                    <div class="flex items-stretch gap-2 text-center">
                        <div class="flex-1 rounded-lg border border-base-300 p-3">
                            <div class="text-xs text-base-content/50 uppercase tracking-wide">Before</div>
                            <div class="text-2xl font-bold tabular-nums">{{ $before }}</div>
                        </div>
                        <div class="flex items-center justify-center px-1">
                            <span @class([
                                'text-lg font-bold tabular-nums',
                                'text-success' => $viewMovement->quantity > 0,
                                'text-error'   => $viewMovement->quantity < 0,
                            ])>
                                {{ $viewMovement->quantity > 0 ? '+' : '' }}{{ $viewMovement->quantity }}
                            </span>
                        </div>
                        <div class="flex-1 rounded-lg border border-base-300 p-3">
                            <div class="text-xs text-base-content/50 uppercase tracking-wide">After</div>
                            <div class="text-2xl font-bold tabular-nums">{{ $after }}</div>
                        </div>
                    </div>
                @else
                    {{-- Honest about what cannot be known. Replaying the log to
                         invent a figure would be a guess: batch quantities can
                         also be corrected directly, and those are not
                         movements. --}}
                    <div class="rounded-lg border border-base-300 bg-base-200/40 p-3 text-sm">
                        <span class="font-semibold">No running balance for this one.</span>
                        <span class="text-base-content/70">
                            It was recorded before balances were kept. Movements from now on carry them.
                        </span>
                    </div>
                @endif

                <div class="divide-y divide-base-200 text-sm">
                    <div class="flex justify-between py-2">
                        <span class="text-base-content/60">What happened</span>
                        <span class="font-medium">{{ ucfirst(str_replace('_', ' ', $viewMovement->type)) }}</span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-base-content/60">Reference</span>
                        <span class="font-mono text-right">{{ $viewMovement->reference ?: '—' }}</span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-base-content/60">When</span>
                        <span>{{ $viewMovement->created_at->format('j M Y, g:ia') }}</span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-base-content/60">By</span>
                        <span>{{ $viewMovement->user?->name ?? '—' }}</span>
                    </div>
                    @if($viewMovement->fromLocation || $viewMovement->toLocation)
                        <div class="flex justify-between py-2">
                            <span class="text-base-content/60">Moved</span>
                            <span>
                                {{ $viewMovement->fromLocation?->name ?? '—' }}
                                &rarr; {{ $viewMovement->toLocation?->name ?? '—' }}
                            </span>
                        </div>
                    @endif
                    @if($viewMovement->note)
                        <div class="py-2">
                            <div class="text-base-content/60 mb-1">Note</div>
                            <div>{{ $viewMovement->note }}</div>
                        </div>
                    @endif
                </div>

                @if($viewMovement->batch)
                    <div class="rounded-lg border border-base-300 bg-base-200/40 p-3 text-sm">
                        This batch holds
                        <strong class="tabular-nums">{{ $viewMovement->batch->quantity }}</strong>
                        now.
                        @if($after !== null && $viewMovement->batch->quantity !== (int) $after)
                            It has moved again since.
                        @endif
                    </div>
                @endif
            </div>

            <x-slot:actions>
                <x-button label="Close" wire:click="closeDetail" class="btn-ghost" />
            </x-slot:actions>
        @endif
    </x-drawer>
</div>
