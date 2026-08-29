{{-- Polled rather than left until the page is reloaded: the shelf empties as
     the till rings, and a figure that only moves on refresh reads as broken.
     Ten seconds, not five - stock does not change as fast as a queue does. --}}
<div wire:poll.10s>
    <x-card title="Potential Profit" subtitle="What is on the shelf right now, if it all sold" class="mb-4">
        <div class="grid grid-cols-3 gap-2">
            <div class="text-center p-2 sm:p-4 bg-base-200 rounded-lg">
                <div class="text-xs text-base-content/60">Revenue</div>
                <div class="text-sm sm:text-xl font-bold text-primary tabular-nums">
                    ₦{{ number_format($potentialRevenue, 0) }}
                </div>
            </div>
            <div class="text-center p-2 sm:p-4 bg-base-200 rounded-lg">
                <div class="text-xs text-base-content/60">Cost</div>
                <div class="text-sm sm:text-xl font-bold text-error tabular-nums">
                    ₦{{ number_format($potentialCost, 0) }}
                </div>
            </div>
            <div class="text-center p-2 sm:p-4 bg-base-200 rounded-lg">
                <div class="text-xs text-base-content/60">Profit</div>
                <div class="text-sm sm:text-xl font-bold text-success tabular-nums">
                    ₦{{ number_format($potentialProfit, 0) }}
                </div>
            </div>
        </div>

        {{-- Unpriced stock counts as nothing towards revenue, which quietly
             makes the figure too low. Better said than discovered. --}}
        @if($unpricedUnits > 0)
            <div class="alert alert-warning py-2 mt-3 text-sm gap-2">
                <x-icon name="o-exclamation-triangle" class="w-4 h-4 shrink-0" />
                <span>
                    {{ number_format($unpricedUnits) }} {{ Str::plural('unit', $unpricedUnits) }}
                    across {{ $unpricedProducts }} {{ Str::plural('product', $unpricedProducts) }}
                    have no selling price, so they add nothing to the figure above.
                    <a href="{{ route('products.index') }}" class="link">Price them</a>
                    and it will rise.
                </span>
            </div>
        @endif

        <p class="text-xs text-base-content/50 mt-2">
            Updates on its own as stock moves. Expired stock is left out of all three,
            and the date filter above does not apply here.
        </p>

        <div class="mt-2">
            <x-button label="View Reports" link="{{ route('reports.index') }}" class="btn-xs btn-ghost" icon="o-arrow-right" />
        </div>
    </x-card>
</div>
