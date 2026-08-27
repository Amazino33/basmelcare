<div>
    <x-header title="Can't Sell" subtitle="What customers asked for and what has run out" size="text-xl">
        <x-slot:middle class="!justify-end">
            <x-input icon="o-magnifying-glass" placeholder="Search..."
                     wire:model.live.debounce="search" clearable />
        </x-slot:middle>
    </x-header>

    <div class="flex flex-wrap gap-2 mb-4">
        @foreach([
            'all'   => ['Everything', $askedCount + $stockCount],
            'asked' => ['Asked for, not stocked', $askedCount],
            'stock' => ['Out of stock', $stockCount],
        ] as $key => [$label, $count])
            <button type="button" wire:click="$set('filter', '{{ $key }}')"
                class="btn btn-sm {{ $filter === $key ? 'btn-primary' : 'btn-ghost bg-base-200' }}">
                {{ $label }}
                <span class="badge badge-sm {{ $filter === $key ? 'badge-neutral' : 'badge-ghost' }}">{{ $count }}</span>
            </button>
        @endforeach
    </div>

    @unless($canSource)
        <div class="alert alert-warning py-2 mb-4 text-sm gap-2">
            <x-icon name="o-exclamation-triangle" class="w-4 h-4 shrink-0" />
            <span>You can see what is missing, but only buying staff can mark something as sourced.</span>
        </div>
    @endunless

    {{-- Asked for and never stocked. The strongest buying signal you have,
         because somebody stood at the counter and wanted it. --}}
    @if($filter !== 'stock')
        <x-card title="Asked for, not stocked" subtitle="Searches at the till that found nothing" class="mb-4">
            @forelse($asked as $miss)
                <div class="flex items-center gap-3 py-2 border-b border-base-200 last:border-0"
                     wire:key="asked-{{ $miss->id }}">
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-sm">{{ $miss->term }}</div>
                        <div class="text-xs text-base-content/50">
                            asked {{ $miss->times }} {{ Str::plural('time', $miss->times) }}
                            @if($miss->last_searched_at)
                                &middot; last {{ $miss->last_searched_at->diffForHumans() }}
                            @endif
                            @if($miss->lastUser)
                                &middot; by {{ $miss->lastUser->name }}
                            @endif
                        </div>
                    </div>

                    @if($canSource)
                        <x-button label="Got it" icon="o-check"
                                  wire:click="markSearchSourced({{ $miss->id }})"
                                  class="btn-xs btn-outline btn-success shrink-0"
                                  spinner="markSearchSourced({{ $miss->id }})" />
                    @endif
                </div>
            @empty
                <div class="text-center py-6 text-base-content/50 text-sm">
                    Nothing outstanding. Every request has been dealt with.
                </div>
            @endforelse
        </x-card>
    @endif

    {{-- In the catalogue, nothing on the shelf. --}}
    @if($filter !== 'asked')
        <x-card title="Out of stock" subtitle="In the catalogue with nothing left">
            @forelse($outOfStock as $product)
                <div class="flex items-center gap-3 py-2 border-b border-base-200 last:border-0"
                     wire:key="stock-{{ $product->id }}">
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-sm truncate">{{ $product->name }}</div>
                        <div class="text-xs text-base-content/50">
                            {{ $product->category->name ?? 'Uncategorised' }}
                            &middot; sells at ₦{{ number_format($product->selling_price, 2) }}
                        </div>
                    </div>

                    @if($canSource)
                        <x-button label="Got it" icon="o-check"
                                  wire:click="markProductSourced({{ $product->id }})"
                                  class="btn-xs btn-outline btn-success shrink-0"
                                  spinner="markProductSourced({{ $product->id }})" />
                    @endif
                </div>
            @empty
                <div class="text-center py-6 text-base-content/50 text-sm">
                    Everything in the catalogue has stock.
                </div>
            @endforelse
        </x-card>
    @endif

    @if($sourced->isNotEmpty())
        <x-card title="Recently marked as sourced" class="mt-4">
            @foreach($sourced as $miss)
                <div class="flex items-center gap-3 py-1.5 border-b border-base-200 last:border-0"
                     wire:key="done-{{ $miss->id }}">
                    <div class="flex-1 min-w-0">
                        <span class="text-sm">{{ $miss->term }}</span>
                        <span class="text-xs text-base-content/50">
                            &middot; {{ $miss->sourcedBy->name ?? 'someone' }},
                            {{ $miss->sourced_at?->diffForHumans() }}
                        </span>
                    </div>

                    @if($canSource)
                        <x-button label="Undo" wire:click="undoSearch({{ $miss->id }})"
                                  class="btn-xs btn-ghost shrink-0" />
                    @endif
                </div>
            @endforeach
        </x-card>
    @endif

    <div class="mt-6 rounded-lg border border-base-300 bg-base-200/40 p-4 text-sm">
        <p class="font-semibold mb-1">What "Got it" does</p>
        <p class="text-base-content/70">
            It takes the entry off this list and records who marked it and when. It does
            <strong>not</strong> create the product or add stock &mdash; do those on the
            Products page, so the catalogue only changes when you mean it to.
        </p>
        <p class="text-base-content/60 mt-2">
            The mark clears itself. An out-of-stock product reappears here the next time it
            runs out, and a request comes back if somebody asks again and still finds nothing.
        </p>
    </div>
</div>
