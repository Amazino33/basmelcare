<div>
    <x-header title="Money Trail" subtitle="Who changed prices, costs, coupons and settings" size="text-xl" />

    {{-- Filters --}}
    <x-card class="mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <x-select label="What changed" wire:model.live="type" placeholder="Everything"
                :options="collect($types)->map(fn($label, $class) => ['id' => $class, 'name' => $label])->values()"
                option-value="id" option-label="name" />

            <x-select label="Who" wire:model.live="userId" placeholder="Anyone"
                :options="$staff" option-value="id" option-label="name" />

            <x-input label="From" wire:model.live="from" type="date" />
            <x-input label="To" wire:model.live="to" type="date" :max="today()->format('Y-m-d')" />

            <x-input label="Search" wire:model.live.debounce.400ms="search"
                placeholder="Name or field…" icon="o-magnifying-glass" clearable />
        </div>

        <div class="mt-3">
            <x-button label="Reset filters" wire:click="resetFilters" class="btn-ghost btn-xs" icon="o-arrow-path" />
        </div>
    </x-card>

    @if($logs->isEmpty())
        <x-card>
            <div class="text-center py-10 text-base-content/50">
                <x-icon name="o-shield-check" class="w-12 h-12 mx-auto mb-3 opacity-30" />
                <p class="font-semibold">No changes recorded</p>
                <p class="text-sm mt-1">Nothing matching these filters was changed in this period.</p>
            </div>
        </x-card>
    @else
        {{-- Mobile: cards. Desktop: table. --}}
        <div class="space-y-2 lg:hidden">
            @foreach($logs as $log)
                <x-card class="!p-3">
                    <div class="flex justify-between items-start gap-2 mb-1">
                        <div class="min-w-0">
                            <span class="badge badge-ghost badge-sm">{{ $log->typeLabel() }}</span>
                            <span class="font-semibold text-sm ml-1 break-words">{{ $log->auditable_label ?? '—' }}</span>
                        </div>
                        <span class="text-xs text-base-content/50 shrink-0">{{ $log->created_at?->format('d M H:i') }}</span>
                    </div>

                    <div class="text-sm">
                        @if($log->event === 'updated')
                            <span class="text-base-content/60">{{ $log->fieldLabel() }}:</span>
                            <span class="line-through text-base-content/50">{{ $log->old_value ?? '—' }}</span>
                            <span class="mx-1">&rarr;</span>
                            <span class="font-semibold text-primary">{{ $log->new_value ?? '—' }}</span>
                        @else
                            <span class="badge badge-sm {{ $log->event === 'created' ? 'badge-success' : 'badge-error' }}">
                                {{ ucfirst($log->event) }}
                            </span>
                        @endif
                    </div>

                    <div class="text-xs text-base-content/60 mt-1">
                        by {{ $log->user?->name ?? 'System' }}
                    </div>
                </x-card>
            @endforeach
        </div>

        <div class="hidden lg:block bg-base-100 rounded-xl shadow-sm overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Who</th>
                        <th>What</th>
                        <th>Field</th>
                        <th class="text-right">Was</th>
                        <th class="text-right">Now</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $log)
                        <tr class="hover">
                            <td class="whitespace-nowrap text-base-content/70">
                                {{ $log->created_at?->format('d M Y H:i') }}
                            </td>
                            <td class="font-medium">{{ $log->user?->name ?? 'System' }}</td>
                            <td>
                                <span class="badge badge-ghost badge-sm">{{ $log->typeLabel() }}</span>
                                <span class="ml-1">{{ $log->auditable_label ?? '—' }}</span>
                            </td>
                            <td>
                                @if($log->event === 'updated')
                                    {{ $log->fieldLabel() }}
                                @else
                                    <span class="badge badge-sm {{ $log->event === 'created' ? 'badge-success' : 'badge-error' }}">
                                        {{ ucfirst($log->event) }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-right tabular-nums text-base-content/50 line-through">
                                {{ $log->event === 'updated' ? ($log->old_value ?? '—') : '' }}
                            </td>
                            <td class="text-right tabular-nums font-semibold text-primary">
                                {{ $log->event === 'updated' ? ($log->new_value ?? '—') : '' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $logs->links() }}</div>
    @endif
</div>
