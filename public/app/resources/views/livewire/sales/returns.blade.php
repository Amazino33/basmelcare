<div>
    <x-header title="Returns" subtitle="What came back, and what it was refunded with" size="text-xl" />

    <div class="flex flex-col sm:flex-row gap-2 mb-4">
        <x-input wire:model.live.debounce.300ms="search" placeholder="Product, customer or invoice"
                 icon="o-magnifying-glass" class="flex-1" clearable />
        <x-select wire:model.live="period" class="sm:w-40"
                  :options="[
                      ['id' => 'today', 'name' => 'Today'],
                      ['id' => 'week',  'name' => 'This week'],
                      ['id' => 'month', 'name' => 'This month'],
                      ['id' => 'year',  'name' => 'This year'],
                      ['id' => 'all',   'name' => 'Everything'],
                  ]" option-value="id" option-label="name" />
        <x-select wire:model.live="methodFilter" class="sm:w-44"
                  :options="[
                      ['id' => 'all',    'name' => 'Cash and credit'],
                      ['id' => 'cash',   'name' => 'Refunded in cash'],
                      ['id' => 'credit', 'name' => 'Given as credit'],
                  ]" option-value="id" option-label="name" />
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <x-card>
            <div class="text-xs text-base-content/50">Returns</div>
            <div class="text-xl font-bold tabular-nums">{{ number_format($count) }}</div>
        </x-card>
        <x-card>
            <div class="text-xs text-base-content/50">Value returned</div>
            <div class="text-xl font-bold tabular-nums">&#8358;{{ number_format($total, 2) }}</div>
        </x-card>
        <x-card>
            {{-- Apart on purpose: cash left the drawer, credit is owed and
                 leaves it later when the customer draws it. --}}
            <div class="text-xs text-base-content/50">Paid in cash</div>
            <div class="text-xl font-bold tabular-nums text-error">&#8358;{{ number_format($cash, 2) }}</div>
        </x-card>
        <x-card>
            <div class="text-xs text-base-content/50">Given as credit</div>
            <div class="text-xl font-bold tabular-nums">&#8358;{{ number_format($credit, 2) }}</div>
        </x-card>
    </div>

    @if($units > 0)
        <p class="text-sm text-base-content/60 mb-3">
            {{ number_format($units) }} {{ Str::plural('unit', $units) }} went back on the shelf.
            Tap a return to see the items and the batch each went back to.
        </p>
    @endif

    @forelse($returns as $return)
        <x-card class="mb-2 cursor-pointer hover:border-primary/40 border border-transparent transition-colors"
                wire:click="viewReturn({{ $return->id }})">
            <div class="flex flex-col sm:flex-row sm:items-start gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-mono text-sm font-semibold">
                            RT-{{ str_pad($return->id, 5, '0', STR_PAD_LEFT) }}
                        </span>
                        <span class="badge badge-sm {{ $return->isCash() ? 'badge-error' : 'badge-ghost' }}">
                            {{ $return->isCash() ? 'Cash' : 'Credit' }}
                        </span>
                        <span class="text-xs text-base-content/50">
                            against {{ $return->sale?->invoice_number ?? 'sale #' . $return->sale_id }}
                        </span>
                    </div>

                    <div class="text-sm text-base-content/70 mt-1">
                        {{ $return->sale?->customer?->name ?? 'Walk-in customer' }}
                        &middot; {{ $return->processor?->name ?? 'unknown' }}
                        &middot; {{ $return->created_at->format('j M Y, g:ia') }}
                    </div>

                    <div class="text-xs text-base-content/60 mt-1">
                        @foreach($return->items as $item)
                            {{ $item->quantity_returned }}&times; {{ $item->product?->name ?? 'item' }}@if(! $loop->last), @endif
                        @endforeach
                    </div>
                </div>

                <div class="sm:text-right shrink-0">
                    <div class="text-lg font-bold tabular-nums">&#8358;{{ number_format($return->total_credit, 2) }}</div>
                </div>
            </div>
        </x-card>
    @empty
        <x-card>
            <div class="text-center py-8">
                <x-icon name="o-arrow-uturn-left" class="w-10 h-10 mx-auto text-base-content/20" />
                <p class="text-base-content/60 mt-2">Nothing was returned in this period.</p>
            </div>
        </x-card>
    @endforelse

    {{ $returns->links() }}

    <x-drawer wire:model="detailDrawer" title="Return" right class="w-96 lg:w-1/3">
        @if($viewReturn)
            <div class="space-y-4">
                <div>
                    <div class="font-mono font-bold text-lg">
                        RT-{{ str_pad($viewReturn->id, 5, '0', STR_PAD_LEFT) }}
                    </div>
                    <div class="text-sm text-base-content/60">
                        {{ $viewReturn->refundLabel() }} &mdash;
                        &#8358;{{ number_format($viewReturn->total_credit, 2) }}
                    </div>
                </div>

                <div class="divide-y divide-base-200 text-sm">
                    <div class="flex justify-between py-2">
                        <span class="text-base-content/60">Against</span>
                        <span class="font-mono">{{ $viewReturn->sale?->invoice_number ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-base-content/60">Customer</span>
                        <span>{{ $viewReturn->sale?->customer?->name ?? 'Walk-in' }}</span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-base-content/60">Processed by</span>
                        <span>{{ $viewReturn->processor?->name ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-base-content/60">When</span>
                        <span>{{ $viewReturn->created_at->format('j M Y, g:ia') }}</span>
                    </div>
                    @if($viewReturn->reason)
                        <div class="py-2">
                            <div class="text-base-content/60 mb-1">Reason</div>
                            <div>{{ $viewReturn->reason }}</div>
                        </div>
                    @endif
                </div>

                {{-- Which batch each item went back to, so a stock figure that
                     looks wrong can be traced to the shelf it belongs on. --}}
                <div>
                    <div class="text-xs uppercase tracking-wide text-base-content/50 mb-2">Items returned</div>
                    <div class="space-y-2">
                        @foreach($viewReturn->items as $item)
                            <div class="rounded-lg border border-base-300 p-3">
                                <div class="flex justify-between gap-2">
                                    <span class="font-medium">{{ $item->product?->name ?? 'Product removed' }}</span>
                                    <span class="tabular-nums font-bold">{{ $item->quantity_returned }}</span>
                                </div>
                                <div class="text-xs text-base-content/60 mt-1">
                                    Back into batch
                                    <span class="font-mono">{{ $item->batch?->batch_number ?? '—' }}</span>
                                    @if($item->batch)
                                        &middot; that batch now holds
                                        <span class="tabular-nums font-medium">{{ $item->batch->quantity }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <x-slot:actions>
                <a href="{{ route('return.receipt', $viewReturn->id) }}" target="_blank" class="btn btn-ghost btn-sm">
                    Slip
                </a>
                <x-button label="Close" wire:click="closeDetail" class="btn-ghost" />
            </x-slot:actions>
        @endif
    </x-drawer>
</div>
