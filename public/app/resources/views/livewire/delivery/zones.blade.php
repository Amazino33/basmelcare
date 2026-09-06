<div>
    <x-header title="Delivery Areas" subtitle="Where the shop delivers to, and what it charges">
        <x-slot:actions>
            @if($this->canSetPrices())
                <x-button label="Add area" wire:click="create" icon="o-plus" class="btn-primary" />
            @endif
        </x-slot:actions>
    </x-header>

    @unless($this->canSetPrices())
        <div class="alert alert-info py-2 text-sm mb-4">
            <x-icon name="o-eye" class="w-4 h-4 shrink-0" />
            You can see the areas and their charges. Changing them is a manager's job.
        </div>
    @endunless

    {{-- The two figures that apply to every area, kept beside them rather
         than buried in the settings page, because they are read together. --}}
    <x-card class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <x-checkbox label="Offer delivery on the shop"
                        wire:model="delivery_enabled"
                        hint="Off makes the online shop collection only" />

            <x-input label="Free delivery over"
                     wire:model="delivery_free_over"
                     prefix="₦" type="number" step="1" min="0"
                     hint="0 means the charge always applies" />

            @if($this->canSetPrices())
                <div>
                    <x-button label="Save" wire:click="saveSettings" class="btn-primary" spinner="saveSettings" />
                </div>
            @endif
        </div>
    </x-card>

    @if($zones->isEmpty())
        <x-card>
            <div class="text-center py-8">
                <x-icon name="o-truck" class="w-10 h-10 mx-auto text-base-content/20" />
                <p class="font-semibold mt-3">No delivery areas yet</p>
                <p class="text-sm text-base-content/60 mt-1">
                    Until one is added, the online shop is collection only.
                </p>
            </div>
        </x-card>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($zones as $zone)
                <div @class([
                    'card bg-base-100 border p-4',
                    'border-base-200' => $zone->is_active,
                    'border-base-300 opacity-60' => ! $zone->is_active,
                ])>
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-semibold truncate">{{ $zone->name }}</p>
                            <p class="text-lg font-bold text-primary">
                                {{ $zone->fee > 0 ? '₦' . number_format($zone->fee, 0) : 'Free' }}
                            </p>
                        </div>

                        @unless($zone->is_active)
                            <x-badge value="Off" class="badge-ghost badge-sm shrink-0" />
                        @endunless
                    </div>

                    @if($zone->note)
                        <p class="text-xs text-base-content/60 mt-1">{{ $zone->note }}</p>
                    @endif

                    <p class="text-xs text-base-content/50 mt-2">
                        {{ $zone->orders_count }} {{ Str::plural('order', $zone->orders_count) }} delivered here
                    </p>

                    @if($this->canSetPrices())
                        <div class="flex gap-1 mt-3 pt-3 border-t border-base-200">
                            <x-button icon="o-pencil" label="Edit" wire:click="edit({{ $zone->id }})" class="btn-xs btn-ghost" />
                            <x-button :icon="$zone->is_active ? 'o-pause' : 'o-play'"
                                      :label="$zone->is_active ? 'Turn off' : 'Turn on'"
                                      wire:click="toggleActive({{ $zone->id }})" class="btn-xs btn-ghost" />
                            @if($zone->orders_count === 0)
                                <x-button icon="o-trash" wire:click="delete({{ $zone->id }})"
                                          wire:confirm="Remove this area?" class="btn-xs btn-ghost text-error ml-auto" />
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <x-modal wire:model="modal" title="{{ $zoneId ? 'Edit area' : 'New delivery area' }}">
        <x-form wire:submit="save">
            <x-input label="Area name" wire:model="name" placeholder="e.g. Ikeja, Lekki Phase 1" />

            <x-input label="Delivery charge" wire:model="fee" prefix="₦" type="number" step="1" min="0"
                     hint="0 delivers there for nothing" />

            <x-input label="Note for the customer" wire:model="note"
                     placeholder="e.g. Same day if ordered before 4pm"
                     hint="Optional — shown under the choice at checkout" />

            <x-input label="Position in the list" wire:model="sort_order" type="number" min="0"
                     hint="Lower shows first. Put the busy areas at the top." />

            <x-checkbox label="Offered at checkout" wire:model="is_active" />

            <x-slot:actions>
                <x-button label="Cancel" wire:click="$set('modal', false)" />
                <x-button label="Save" class="btn-primary" type="submit" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
