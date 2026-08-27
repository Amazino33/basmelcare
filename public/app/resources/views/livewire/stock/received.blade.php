<div>
    <x-header title="Stock Received" subtitle="Everything taken into stock, grouped by day and by who entered it" size="text-xl">
        <x-slot:middle class="!justify-end">
            <x-input icon="o-magnifying-glass" placeholder="Search a product..."
                     wire:model.live.debounce="search" clearable />
        </x-slot:middle>
    </x-header>

    <div class="flex flex-wrap gap-2 mb-4">
        @foreach(['deliveries' => 'Deliveries', 'opening' => 'Opening stock'] as $key => $label)
            <button type="button" wire:click="$set('view', '{{ $key }}')"
                class="btn btn-sm {{ $view === $key ? 'btn-primary' : 'btn-ghost bg-base-200' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <x-card class="mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
            @if($view === 'deliveries')
                <x-input label="From" wire:model.live="dateFrom" type="date" />
                <x-input label="To" wire:model.live="dateTo" type="date" />

                <div class="sm:col-span-2 flex gap-6 justify-end text-right">
                    <div>
                        <div class="text-xs text-base-content/50">Units taken in</div>
                        <div class="text-lg font-bold tabular-nums">{{ number_format($totalUnits) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-base-content/50">At cost</div>
                        <div class="text-lg font-bold text-primary tabular-nums">₦{{ number_format($totalValue, 2) }}</div>
                    </div>
                </div>
            @else
                {{-- No date range: the question is what the pharmacy started
                     with, which means reaching past any chosen window. --}}
                <div class="sm:col-span-4 flex flex-wrap gap-6 justify-end text-right">
                    <div>
                        <div class="text-xs text-base-content/50">Products</div>
                        <div class="text-lg font-bold tabular-nums">{{ number_format($openingCount) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-base-content/50">Units first stocked</div>
                        <div class="text-lg font-bold tabular-nums">{{ number_format($openingUnits) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-base-content/50">At cost</div>
                        <div class="text-lg font-bold text-primary tabular-nums">₦{{ number_format($openingValue, 2) }}</div>
                    </div>
                </div>
            @endif
        </div>
    </x-card>

    @if($view === 'opening')
        <div class="space-y-3">
            @forelse($opening as $day => $lines)
                <x-card>
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-base-200 pb-2 mb-2">
                        <span class="font-semibold">{{ \Carbon\Carbon::parse($day)->format('D, d M Y') }}</span>
                        <span class="text-sm">
                            <span class="text-base-content/60">{{ $lines->count() }} {{ Str::plural('product', $lines->count()) }} first stocked</span>
                            <span class="font-bold text-primary ml-2 tabular-nums">
                                ₦{{ number_format($lines->sum(fn($m) => $m->quantity * (float) ($m->batch->cost_price ?? 0)), 2) }}
                            </span>
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Batch</th>
                                    <th class="text-right">Opening qty</th>
                                    <th class="text-right">Unit cost</th>
                                    <th class="text-right">Value</th>
                                    <th class="text-right">On hand now</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($lines as $line)
                                    <tr>
                                        <td>
                                            {{ $line->batch->product->name ?? 'Deleted product' }}
                                            <div class="text-xs text-base-content/50">
                                                {{ $line->batch->product->category->name ?? '—' }}
                                            </div>
                                        </td>
                                        <td class="text-xs">{{ $line->batch->batch_number ?? '—' }}</td>
                                        <td class="text-right tabular-nums font-semibold">{{ number_format($line->quantity) }}</td>
                                        <td class="text-right tabular-nums">₦{{ number_format((float) ($line->batch->cost_price ?? 0), 2) }}</td>
                                        <td class="text-right tabular-nums">
                                            ₦{{ number_format($line->quantity * (float) ($line->batch->cost_price ?? 0), 2) }}
                                        </td>
                                        {{-- What is left of that first batch today, so the two are not confused --}}
                                        <td class="text-right tabular-nums text-base-content/50">
                                            {{ number_format((int) ($line->batch->quantity ?? 0)) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @empty
                <x-card>
                    <div class="text-center py-10 text-base-content/50">
                        <x-icon name="o-inbox" class="w-12 h-12 mx-auto mb-3 opacity-30" />
                        <p class="font-semibold">No stock has been taken in yet</p>
                    </div>
                </x-card>
            @endforelse
        </div>

        <p class="text-xs text-base-content/50 mt-4">
            The first time each product was ever stocked, at the quantity and cost it came
            in at. <strong>Opening qty</strong> is what went in and does not change;
            <strong>on hand now</strong> is what is left of that same batch today.
            Products stocked for the first time later appear under their own date, so the
            original startup load reads as one block.
        </p>

        <p class="text-xs text-base-content/50 mt-2">
            Stock entered before who-did-it was recorded cannot be attributed to anyone,
            and lines entered then do not say whether they came from Quick Add or Add Batch.
            That was never written down and cannot be recovered.
        </p>
    @else

    @if($unattributed > 0)
        {{-- The gap is itself worth showing rather than quietly hiding --}}
        <div class="alert alert-warning py-2 mb-4 text-sm gap-2">
            <x-icon name="o-exclamation-triangle" class="w-4 h-4 shrink-0" />
            <span>
                {{ $unattributed }} {{ Str::plural('line', $unattributed) }} in this period has no one recorded against it.
                Stock entered before this was tracked cannot be attributed.
            </span>
        </div>
    @endif

    <div class="space-y-3">
        @forelse($intakes as $intake)
            <x-card>
                <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-base-200 pb-2 mb-2">
                    <div>
                        <span class="font-semibold">{{ $intake['date']->format('D, d M Y') }}</span>
                        <span class="text-sm text-base-content/60">
                            &middot; {{ $intake['by'] ?? 'not recorded' }}
                        </span>
                    </div>
                    <div class="text-sm">
                        <span class="text-base-content/60">{{ $intake['lines']->count() }} {{ Str::plural('item', $intake['lines']->count()) }}</span>
                        @if($intake['newCount'] > 0)
                            <span class="badge badge-success badge-sm ml-1">{{ $intake['newCount'] }} new</span>
                        @endif
                        <span class="font-bold text-primary ml-2 tabular-nums">₦{{ number_format($intake['value'], 2) }}</span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Batch</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Unit cost</th>
                                <th class="text-right">Line cost</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($intake['lines'] as $line)
                                <tr>
                                    <td>
                                        @if($line->reference === 'Opening stock')
                                            <span class="badge badge-success badge-xs mr-1">NEW</span>
                                        @endif
                                        {{ $line->batch->product->name ?? 'Deleted product' }}
                                        <div class="text-xs text-base-content/50">
                                            {{ $line->batch->product->category->name ?? '—' }}
                                        </div>
                                    </td>
                                    <td class="text-xs">
                                        {{ $line->batch->batch_number ?? '—' }}
                                        @if($line->batch?->expiry_date)
                                            <div class="text-base-content/50">exp {{ $line->batch->expiry_date->format('M Y') }}</div>
                                        @endif
                                    </td>
                                    <td class="text-right tabular-nums">{{ number_format($line->quantity) }}</td>
                                    <td class="text-right tabular-nums">₦{{ number_format((float) ($line->batch->cost_price ?? 0), 2) }}</td>
                                    <td class="text-right tabular-nums font-semibold">
                                        ₦{{ number_format($line->quantity * (float) ($line->batch->cost_price ?? 0), 2) }}
                                    </td>
                                    <td class="text-xs text-base-content/50">{{ $line->created_at->format('g:i A') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @empty
            <x-card>
                <div class="text-center py-10 text-base-content/50">
                    <x-icon name="o-inbox" class="w-12 h-12 mx-auto mb-3 opacity-30" />
                    <p class="font-semibold">Nothing taken into stock in this period</p>
                    <p class="text-sm mt-1">Try a wider date range.</p>
                </div>
            </x-card>
        @endforelse
    </div>

    <p class="text-xs text-base-content/50 mt-4">
        A delivery is not one row in the system &mdash; it is however many lines one person
        entered in one sitting, so lines are grouped by day and by who entered them.
        <strong>NEW</strong> marks a product that had no stock in the catalogue before.
        Sales and returns move stock too, and are deliberately not shown here.
    </p>
    @endif
</div>
