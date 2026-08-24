<div>
    <x-header title="Prescription Medicines" subtitle="Which drugs may not be handed over without a prescription" size="text-xl">
        <x-slot:middle class="!justify-end">
            <x-input icon="o-magnifying-glass" placeholder="Search medicines..."
                     wire:model.live.debounce="search" clearable />
        </x-slot:middle>
    </x-header>

    @unless($canDecide)
        <div class="alert alert-warning py-2 mb-4 text-sm gap-2">
            <x-icon name="o-exclamation-triangle" class="w-4 h-4 shrink-0" />
            <span>Only a pharmacist can change these. You can see what is marked, but not change it.</span>
        </div>
    @endunless

    <div class="flex flex-wrap items-center gap-2 mb-4">
        @foreach([
            'all'      => 'All',
            'marked'   => 'Needs a prescription',
            'unmarked' => 'Over the counter',
        ] as $key => $label)
            <button type="button" wire:click="$set('filter', '{{ $key }}')"
                class="btn btn-sm {{ $filter === $key ? 'btn-primary' : 'btn-ghost bg-base-200' }}">
                {{ $label }}
                @if($key === 'marked')
                    <span class="badge badge-sm {{ $filter === $key ? 'badge-neutral' : 'badge-ghost' }}">{{ $markedCount }}</span>
                @endif
            </button>
        @endforeach

        <span class="text-xs text-base-content/50 ml-auto">
            {{ $markedCount }} of {{ $totalCount }} marked
        </span>
    </div>

    <div class="space-y-1">
        @forelse($products as $product)
            <x-card class="!p-3">
                <div class="flex items-center gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-sm truncate">{{ $product->name }}</div>
                        <div class="text-xs text-base-content/50">
                            {{ $product->category->name ?? 'Uncategorised' }}
                            @if($product->requires_prescription)
                                &middot; <span class="text-error font-semibold">Prescription only</span>
                            @endif
                        </div>
                    </div>

                    {{-- Instant, with the row showing it working rather than a page-wide spinner --}}
                    <div class="shrink-0 flex items-center gap-2">
                        <span wire:loading wire:target="toggle({{ $product->id }})"
                              class="loading loading-spinner loading-xs text-primary"></span>

                        <input type="checkbox"
                               class="toggle toggle-sm toggle-error"
                               @checked($product->requires_prescription)
                               @disabled(! $canDecide)
                               wire:click="toggle({{ $product->id }})"
                               wire:loading.attr="disabled"
                               wire:target="toggle({{ $product->id }})" />
                    </div>
                </div>
            </x-card>
        @empty
            <x-card>
                <div class="text-center py-10 text-base-content/50">
                    @if($search)
                        <x-icon name="o-magnifying-glass" class="w-12 h-12 mx-auto mb-3 opacity-30" />
                        <p class="font-semibold">Nothing matches "{{ $search }}"</p>
                    @elseif($filter === 'marked')
                        <x-icon name="o-document-check" class="w-12 h-12 mx-auto mb-3 opacity-30" />
                        <p class="font-semibold">Nothing marked yet</p>
                        <p class="text-sm mt-1">Until a drug is marked here, it can be bought online with no prescription.</p>
                    @else
                        <x-icon name="o-cube" class="w-12 h-12 mx-auto mb-3 opacity-30" />
                        <p class="font-semibold">No medicines in the catalogue</p>
                    @endif
                </div>
            </x-card>
        @endforelse
    </div>

    @if($products->hasPages())
        <div class="mt-4">{{ $products->links() }}</div>
    @endif

    <div class="mt-6 rounded-lg border border-base-300 bg-base-200/40 p-4 text-sm">
        <p class="font-semibold mb-1">What marking a drug does</p>
        <ul class="list-disc list-inside space-y-1 text-base-content/70">
            <li>It shows an <span class="badge badge-error badge-xs align-middle">Rx</span> label in the online shop.</li>
            <li>A customer cannot check out with it until they upload a prescription.</li>
            <li>The order then waits in <strong>Prescriptions</strong> until you approve or reject it.</li>
        </ul>
        <p class="text-xs text-base-content/50 mt-2">
            Selling at the counter is not affected. Nothing stops a cashier ringing one up in person.
        </p>
    </div>
</div>
