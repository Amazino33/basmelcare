<div>
    <x-header title="Financial Records" subtitle="What was earned, what it cost, what moved" size="text-xl" />

    {{-- Period --}}
    <x-card class="mb-4">
        <div class="flex flex-wrap items-end gap-2">
            @foreach([
                'today' => 'Today', 'yesterday' => 'Yesterday', 'this_week' => 'This Week',
                'this_month' => 'This Month', 'last_month' => 'Last Month', 'this_year' => 'This Year',
            ] as $key => $label)
                <button type="button" wire:click="applyPreset('{{ $key }}')"
                    class="btn btn-sm {{ $preset === $key ? 'btn-primary' : 'btn-ghost bg-base-200' }}">
                    {{ $label }}
                </button>
            @endforeach

            <div class="flex items-end gap-2 ml-auto">
                <x-input label="From" wire:model.live="from" type="date" class="input-sm" />
                <x-input label="To" wire:model.live="to" type="date" :max="today()->format('Y-m-d')" class="input-sm" />
            </div>
        </div>
        <p class="text-xs text-base-content/50 mt-2">
            {{ \Carbon\Carbon::parse($from)->format('j M Y') }} –
            {{ \Carbon\Carbon::parse($to)->format('j M Y') }} ·
            {{ number_format($f['saleCount']) }} settled sale(s)
        </p>
    </x-card>

    @if($filtered)
        {{-- The panels below describe ONLY these invoices, so say so loudly. --}}
        <div class="alert alert-info py-2 mb-4 text-sm gap-2">
            <x-icon name="o-funnel" class="w-4 h-4 shrink-0" />
            <span>
                <strong>Filtered view</strong> — every figure below covers the
                <strong>{{ number_format($sales->total()) }}</strong> matching
                {{ \Illuminate\Support\Str::plural('invoice', $sales->total()) }}
                of {{ number_format($totalInPeriod) }} in this period, not the whole period.
            </span>
            <x-button label="Show all" wire:click="clearFilters" class="btn-xs btn-ghost" />
        </div>
    @endif

    {{-- Headline --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <x-stat title="Money In" value="₦{{ number_format($f['collected'], 2) }}"
            description="actually received" icon="o-arrow-down-tray" color="text-success"
            class="text-sm h-full px-3 py-3 sm:px-5 sm:py-4 [&_svg]:w-7 [&_svg]:h-7 sm:[&_svg]:w-9 sm:[&_svg]:h-9 [&_.font-black]:text-base sm:[&_.font-black]:text-xl [&_.whitespace-nowrap]:whitespace-normal [&_.whitespace-nowrap]:leading-tight" />

        <x-stat title="Money Out" value="₦{{ number_format($f['paidOut'], 2) }}"
            description="expenses + stock" icon="o-arrow-up-tray" color="text-error"
            class="text-sm h-full px-3 py-3 sm:px-5 sm:py-4 [&_svg]:w-7 [&_svg]:h-7 sm:[&_svg]:w-9 sm:[&_svg]:h-9 [&_.font-black]:text-base sm:[&_.font-black]:text-xl [&_.whitespace-nowrap]:whitespace-normal [&_.whitespace-nowrap]:leading-tight" />

        <x-stat title="Gross Profit" value="₦{{ number_format($f['gross'], 2) }}"
            description="{{ number_format($f['grossMargin'], 1) }}% margin" icon="o-chart-bar" color="text-primary"
            class="text-sm h-full px-3 py-3 sm:px-5 sm:py-4 [&_svg]:w-7 [&_svg]:h-7 sm:[&_svg]:w-9 sm:[&_svg]:h-9 [&_.font-black]:text-base sm:[&_.font-black]:text-xl [&_.whitespace-nowrap]:whitespace-normal [&_.whitespace-nowrap]:leading-tight" />

        <x-stat title="Net Profit" value="₦{{ number_format($f['netProfit'], 2) }}"
            description="after expenses"
            icon="{{ $f['netProfit'] >= 0 ? 'o-arrow-trending-up' : 'o-arrow-trending-down' }}"
            color="{{ $f['netProfit'] >= 0 ? 'text-success' : 'text-error' }}"
            class="text-sm h-full px-3 py-3 sm:px-5 sm:py-4 [&_svg]:w-7 [&_svg]:h-7 sm:[&_svg]:w-9 sm:[&_svg]:h-9 [&_.font-black]:text-base sm:[&_.font-black]:text-xl [&_.whitespace-nowrap]:whitespace-normal [&_.whitespace-nowrap]:leading-tight" />
    </div>

    @if($f['expenses'] == 0 && $expensesRecorded === 0)
        <div class="alert alert-warning py-2 mb-4 text-sm gap-2">
            <x-icon name="o-exclamation-triangle" class="w-4 h-4 shrink-0" />
            <span>
                No expenses recorded for this period, so <strong>Net Profit equals Gross Profit</strong>.
                Rent, salaries and bills must be entered under Expenses before net profit is real.
            </span>
        </div>
    @endif

    {{-- The two views, deliberately separate --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">

        <x-card title="Trading" subtitle="Did we make money on what we sold?">
            <table class="table table-sm">
                <tbody>
                    <tr>
                        <td>Sales revenue</td>
                        <td class="text-right tabular-nums">₦{{ number_format($f['revenue'], 2) }}</td>
                    </tr>
                    @if($f['refunds'] > 0)
                        <tr class="text-base-content/70">
                            <td class="pl-4">less refunds</td>
                            <td class="text-right tabular-nums text-error">−₦{{ number_format($f['refunds'], 2) }}</td>
                        </tr>
                        <tr class="font-semibold">
                            <td>Net revenue</td>
                            <td class="text-right tabular-nums">₦{{ number_format($f['netRevenue'], 2) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td>Cost of goods sold</td>
                        <td class="text-right tabular-nums text-error">−₦{{ number_format($f['cogs'], 2) }}</td>
                    </tr>
                    <tr class="border-t-2 border-base-300 font-bold">
                        <td>Gross profit</td>
                        <td class="text-right tabular-nums text-primary">
                            ₦{{ number_format($f['gross'], 2) }}
                            <span class="font-normal text-xs text-base-content/50">
                                ({{ number_format($f['grossMargin'], 1) }}%)
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td>Expenses</td>
                        <td class="text-right tabular-nums text-error">−₦{{ number_format($f['expenses'], 2) }}</td>
                    </tr>
                    <tr class="border-t-2 border-base-300 font-bold">
                        <td>Net profit</td>
                        <td class="text-right tabular-nums {{ $f['netProfit'] >= 0 ? 'text-success' : 'text-error' }}">
                            ₦{{ number_format($f['netProfit'], 2) }}
                            <span class="font-normal text-xs text-base-content/50">
                                ({{ number_format($f['netMargin'], 1) }}%)
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </x-card>

        <x-card title="Collected by method" subtitle="How the money was taken">
            @php $m = $f['methods']; @endphp
            <table class="table table-sm">
                <tbody>
                    @foreach(['cash' => 'Cash', 'card' => 'Card', 'transfer' => 'Transfer'] as $key => $label)
                        <tr>
                            <td>
                                {{ $label }}
                                @if($m['debtByMethod'][$key] > 0)
                                    <span class="text-xs text-base-content/50">
                                        (incl. ₦{{ number_format($m['debtByMethod'][$key], 2) }} debt repaid)
                                    </span>
                                @endif
                            </td>
                            <td class="text-right tabular-nums">₦{{ number_format($m['byMethod'][$key], 2) }}</td>
                        </tr>
                    @endforeach

                    <tr class="border-t-2 border-base-300 font-bold">
                        <td>Recorded by method</td>
                        <td class="text-right tabular-nums">₦{{ number_format($m['methodTotal'], 2) }}</td>
                    </tr>

                    @if($m['unrecorded'] > 0)
                        <tr class="text-warning">
                            <td>
                                Method not recorded
                                <span class="text-xs opacity-70 block">
                                    {{ $m['salesTotal'] - $m['salesWithMethod'] }} of {{ $m['salesTotal'] }} sales
                                </span>
                            </td>
                            <td class="text-right tabular-nums">₦{{ number_format($m['unrecorded'], 2) }}</td>
                        </tr>
                    @endif

                    <tr class="border-t border-base-300 text-base-content/70">
                        <td class="text-sm">Settled sales total</td>
                        <td class="text-right tabular-nums text-sm">₦{{ number_format($m['settledTotal'], 2) }}</td>
                    </tr>

                    @if($m['storeCredit'] > 0 || $m['changeGiven'] > 0)
                        <tr><td colspan="2" class="pt-3 pb-0 text-xs uppercase tracking-wide text-base-content/40">Also worth knowing</td></tr>
                        @if($m['storeCredit'] > 0)
                            <tr class="text-base-content/70">
                                <td class="text-sm">Paid with store credit
                                    <span class="text-xs block opacity-70">not new money — taken earlier</span>
                                </td>
                                <td class="text-right tabular-nums text-sm">₦{{ number_format($m['storeCredit'], 2) }}</td>
                            </tr>
                        @endif
                        @if($m['changeGiven'] > 0)
                            <tr class="text-base-content/70">
                                <td class="text-sm">Change handed back
                                    <span class="text-xs block opacity-70">already deducted from cash above</span>
                                </td>
                                <td class="text-right tabular-nums text-sm">₦{{ number_format($m['changeGiven'], 2) }}</td>
                            </tr>
                        @endif
                    @endif
                </tbody>
            </table>

            @if($m['unrecorded'] > 0)
                <div class="flex items-start gap-2 p-2 mt-2 bg-warning/10 rounded-lg text-xs">
                    <x-icon name="o-exclamation-triangle" class="w-4 h-4 shrink-0 mt-0.5 text-warning" />
                    <span>
                        Older sales were recorded before the till stored a payment method,
                        so their money cannot be split by cash, card or transfer.
                        Sales taken from now on will be.
                    </span>
                </div>
            @endif
        </x-card>

        @if($filtered)
            <x-card title="Cash" subtitle="Whole period only">
                <div class="flex items-start gap-2 text-sm text-base-content/70">
                    <x-icon name="o-information-circle" class="w-5 h-5 shrink-0 mt-0.5" />
                    <p>
                        Expenses, stock purchases and debt cannot be tied to a search on
                        invoices, so cash movement is not shown while a filter is active.
                        <button type="button" wire:click="clearFilters" class="link link-primary">Clear the filter</button>
                        to see it.
                    </p>
                </div>
            </x-card>
        @else
        <x-card title="Cash" subtitle="What actually moved in and out?">
            <table class="table table-sm">
                <tbody>
                    <tr>
                        <td>Billed on settled sales</td>
                        <td class="text-right tabular-nums">₦{{ number_format($f['netRevenue'], 2) }}</td>
                    </tr>
                    <tr class="text-base-content/70">
                        <td class="pl-4">less sold on credit</td>
                        <td class="text-right tabular-nums">−₦{{ number_format($f['newDebt'], 2) }}</td>
                    </tr>
                    <tr class="text-base-content/70">
                        <td class="pl-4">plus debt repaid</td>
                        <td class="text-right tabular-nums">+₦{{ number_format($f['debtRepaid'], 2) }}</td>
                    </tr>
                    @if($f['creditPaidOut'] > 0)
                        <tr class="text-base-content/70">
                            <td class="pl-4">less change paid out</td>
                            <td class="text-right tabular-nums">−₦{{ number_format($f['creditPaidOut'], 2) }}</td>
                        </tr>
                    @endif
                    <tr class="border-t-2 border-base-300 font-bold">
                        <td>Money in</td>
                        <td class="text-right tabular-nums text-success">₦{{ number_format($f['collected'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Stock purchases</td>
                        <td class="text-right tabular-nums text-error">−₦{{ number_format($f['stockPurchases'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Expenses</td>
                        <td class="text-right tabular-nums text-error">−₦{{ number_format($f['expenses'], 2) }}</td>
                    </tr>
                    <tr class="border-t-2 border-base-300 font-bold">
                        <td>Net cash movement</td>
                        <td class="text-right tabular-nums {{ $f['netCash'] >= 0 ? 'text-success' : 'text-error' }}">
                            ₦{{ number_format($f['netCash'], 2) }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="text-xs text-base-content/50 mt-2">
                Stock bought is money out now but only becomes a cost when it sells,
                which is why this differs from trading profit.
            </p>
        </x-card>
        @endif
    </div>

    {{-- Every sale behind the numbers --}}
    <x-card title="Every invoice in this period"
            subtitle="Cancelled and unpaid invoices are listed too, so no number is unaccounted for">

            <div class="flex flex-wrap items-end gap-2 mb-4">
                <x-input placeholder="Search invoice, customer, staff or product..."
                         wire:model.live.debounce.400ms="search"
                         icon="o-magnifying-glass" class="input-sm flex-1 min-w-[16rem]" clearable />

                <x-select wire:model.live="statusFilter" class="select-sm"
                    :options="[
                        ['id'=>'all',      'name'=>'All invoices'],
                        ['id'=>'settled',  'name'=>'Settled only'],
                        ['id'=>'cancelled','name'=>'Cancelled only'],
                        ['id'=>'pending',  'name'=>'Unpaid only'],
                    ]" option-value="id" option-label="name" />

                <x-select wire:model.live="methodFilter" class="select-sm"
                    :options="[
                        ['id'=>'all',      'name'=>'Any payment method'],
                        ['id'=>'cash',     'name'=>'Cash taken'],
                        ['id'=>'card',     'name'=>'Card taken'],
                        ['id'=>'transfer', 'name'=>'Transfer taken'],
                    ]" option-value="id" option-label="name" />

                @if($filtered)
                    <x-button label="Clear" wire:click="clearFilters" icon="o-x-mark" class="btn-sm btn-ghost" />
                @endif
            </div>

        @if($f['cancelledCount'] > 0 || $f['pendingCount'] > 0)
            <div class="flex flex-wrap gap-2 mb-3 text-xs">
                @if($f['cancelledCount'] > 0)
                    <span class="badge badge-error badge-sm">{{ $f['cancelledCount'] }} cancelled</span>
                @endif
                @if($f['pendingCount'] > 0)
                    <span class="badge badge-warning badge-sm">{{ $f['pendingCount'] }} unpaid</span>
                @endif
                <span class="text-base-content/50">— shown for completeness, counted as ₦0</span>
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Customer</th>
                        <th>Sold by</th>
                        @if($methodFilter !== 'all')
                            {{-- The chosen method's share of each invoice, so these
                                 add up to the figure in the panel above. --}}
                            <th class="text-right">{{ ucfirst($methodFilter) }}</th>
                        @endif
                        <th class="text-right">Sold for</th>
                        <th class="text-right">Cost</th>
                        <th class="text-right">Profit</th>
                        <th class="text-right">Margin</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales as $sale)
                        @php
                            $settled = in_array($sale->status, ['paid', 'completed']);
                            $net     = $settled ? (float) $sale->total_amount - (float) ($sale->coupon_discount ?? 0) : 0.0;
                            $cost    = $settled ? (float) ($sale->cogs ?? 0) : 0.0;
                            $profit  = $net - $cost;
                        @endphp
                        <tr wire:click="viewSale({{ $sale->id }})"
                            class="hover cursor-pointer {{ $settled ? '' : 'opacity-60' }}">
                            <td class="font-mono text-xs text-primary underline decoration-dotted underline-offset-2">
                                {{ $sale->invoice_number ?? '#' . $sale->id }}
                            </td>
                            <td class="text-xs whitespace-nowrap">{{ $sale->created_at->format('d M H:i') }}</td>
                            <td>
                                <span @class([
                                    'badge badge-sm',
                                    'badge-success' => $sale->status === 'completed',
                                    'badge-info'    => $sale->status === 'paid',
                                    'badge-warning' => $sale->status === 'pending',
                                    'badge-error'   => $sale->status === 'cancelled',
                                    'badge-ghost'   => ! in_array($sale->status, ['completed','paid','pending','cancelled']),
                                ])>{{ ucfirst($sale->status) }}</span>
                            </td>
                            <td class="text-sm">{{ $sale->customer?->name ?? 'Walk-in' }}</td>
                            <td class="text-sm">{{ $sale->user?->name ?? '—' }}</td>

                            @if($methodFilter !== 'all')
                                @php
                                    // Read from the same place the panel figures
                                    // come from, so a split sale shows only its
                                    // share of the chosen method.
                                    $pd = is_string($sale->payment_details)
                                        ? json_decode($sale->payment_details, true)
                                        : $sale->payment_details;
                                    $share = is_array($pd) && is_numeric($pd[$methodFilter] ?? null)
                                        ? (float) $pd[$methodFilter]
                                        : 0.0;
                                @endphp
                                <td class="text-right tabular-nums font-semibold">
                                    ₦{{ number_format($share, 2) }}
                                </td>
                            @endif

                            @if($settled)
                                <td class="text-right tabular-nums">₦{{ number_format($net, 2) }}</td>
                                <td class="text-right tabular-nums text-base-content/60">₦{{ number_format($cost, 2) }}</td>
                                <td class="text-right tabular-nums font-semibold {{ $profit >= 0 ? 'text-success' : 'text-error' }}">
                                    ₦{{ number_format($profit, 2) }}
                                </td>
                                <td class="text-right tabular-nums text-xs text-base-content/60">
                                    {{ $net > 0 ? number_format(($profit / $net) * 100, 1) . '%' : '—' }}
                                </td>
                            @else
                                {{-- Billed value shown for context, but it earns nothing. --}}
                                <td class="text-right tabular-nums text-base-content/40 line-through">
                                    ₦{{ number_format((float) $sale->total_amount, 2) }}
                                </td>
                                <td colspan="3" class="text-right text-xs text-base-content/50 italic">
                                    {{ $sale->status === 'cancelled' ? 'cancelled — not counted' : 'not yet paid — not counted' }}
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $methodFilter === 'all' ? 9 : 10 }}" class="text-center text-base-content/40 py-10">
                                No invoices in this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($sales->hasPages())
            <div class="mt-3">{{ $sales->links() }}</div>
        @endif
    </x-card>

    {{-- Invoice detail: everything needed to check the figure without leaving the page --}}
    <x-drawer wire:model="saleDrawer" right class="w-full sm:w-[34rem] lg:w-[44rem]"
              title="{{ $viewSale?->invoice_number ?? 'Invoice' }}">
        @if($viewSale)
            @php
                $settled = in_array($viewSale->status, ['paid', 'completed']);
                $gross   = (float) $viewSale->total_amount;
                $disc    = (float) ($viewSale->coupon_discount ?? 0);
                $net     = $gross - $disc;
                $cogs    = $viewSale->saleItems->sum(fn($i) => (float) $i->cost_price * $i->quantity);
                $profit  = $net - $cogs;

                $pd = $viewSale->payment_details;
                $pd = is_string($pd) ? json_decode($pd, true) : $pd;
                $pd = is_array($pd) ? $pd : [];
            @endphp

            {{-- Who and when --}}
            <div class="flex flex-wrap items-center gap-2 mb-4">
                <span @class([
                    'badge',
                    'badge-success' => $viewSale->status === 'completed',
                    'badge-info'    => $viewSale->status === 'paid',
                    'badge-warning' => $viewSale->status === 'pending',
                    'badge-error'   => $viewSale->status === 'cancelled',
                ])>{{ ucfirst($viewSale->status) }}</span>
                <span class="text-sm text-base-content/60">
                    {{ $viewSale->created_at->format('D, j M Y · H:i') }}
                </span>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-5 text-sm">
                <div>
                    <div class="text-xs text-base-content/50 uppercase tracking-wide">Customer</div>
                    <div>{{ $viewSale->customer?->name ?? 'Walk-in' }}</div>
                </div>
                <div>
                    <div class="text-xs text-base-content/50 uppercase tracking-wide">Sold by</div>
                    <div>{{ $viewSale->user?->name ?? '—' }}</div>
                </div>
                @if($viewSale->cashier)
                    <div>
                        <div class="text-xs text-base-content/50 uppercase tracking-wide">Payment taken by</div>
                        <div>{{ $viewSale->cashier->name }}</div>
                    </div>
                @endif
                @if($viewSale->paid_at)
                    <div>
                        <div class="text-xs text-base-content/50 uppercase tracking-wide">Paid</div>
                        <div>{{ $viewSale->paid_at->format('j M Y · H:i') }}</div>
                    </div>
                @endif
            </div>

            {{-- What was sold, with the margin on each line --}}
            <div class="text-xs font-semibold uppercase tracking-wide text-base-content/50 mb-2">Items</div>
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Sold at</th>
                            <th class="text-right">Cost</th>
                            <th class="text-right">Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($viewSale->saleItems as $item)
                            @php
                                $lineRevenue = (float) $item->subtotal;
                                $lineCost    = (float) $item->cost_price * $item->quantity;
                                $lineProfit  = $lineRevenue - $lineCost;
                            @endphp
                            <tr>
                                <td class="text-sm">{{ $item->product?->name ?? '—' }}</td>
                                <td class="text-right tabular-nums">{{ $item->quantity }}</td>
                                <td class="text-right tabular-nums">₦{{ number_format($item->unit_price, 2) }}</td>
                                <td class="text-right tabular-nums text-base-content/60">₦{{ number_format($item->cost_price, 2) }}</td>
                                <td class="text-right tabular-nums {{ $lineProfit >= 0 ? 'text-success' : 'text-error' }}">
                                    ₦{{ number_format($lineProfit, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- The figure, and how it was reached --}}
            <div class="mt-4 p-3 rounded-lg bg-base-200/60">
                <table class="table table-sm">
                    <tbody>
                        <tr>
                            <td>Items total</td>
                            <td class="text-right tabular-nums">₦{{ number_format($gross, 2) }}</td>
                        </tr>
                        @if($disc > 0)
                            <tr class="text-base-content/70">
                                <td>Coupon discount</td>
                                <td class="text-right tabular-nums text-error">−₦{{ number_format($disc, 2) }}</td>
                            </tr>
                        @endif
                        <tr class="font-semibold">
                            <td>Customer paid</td>
                            <td class="text-right tabular-nums">₦{{ number_format($net, 2) }}</td>
                        </tr>
                        <tr>
                            <td>Cost of goods</td>
                            <td class="text-right tabular-nums text-error">−₦{{ number_format($cogs, 2) }}</td>
                        </tr>
                        <tr class="border-t-2 border-base-300 font-bold">
                            <td>Profit on this sale</td>
                            <td class="text-right tabular-nums {{ $profit >= 0 ? 'text-success' : 'text-error' }}">
                                ₦{{ number_format($profit, 2) }}
                                <span class="font-normal text-xs text-base-content/50">
                                    ({{ $net > 0 ? number_format(($profit / $net) * 100, 1) : '0.0' }}%)
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                @unless($settled)
                    <p class="text-xs text-warning mt-2">
                        This invoice is {{ $viewSale->status }}, so none of it counts towards the totals.
                    </p>
                @endunless
            </div>

            {{-- How it was paid --}}
            <div class="text-xs font-semibold uppercase tracking-wide text-base-content/50 mt-5 mb-2">Payment</div>
            @if($pd)
                <table class="table table-sm">
                    <tbody>
                        @foreach(['cash' => 'Cash', 'card' => 'Card', 'transfer' => 'Transfer'] as $key => $label)
                            @if(isset($pd[$key]) && is_numeric($pd[$key]))
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td class="text-right tabular-nums">₦{{ number_format((float) $pd[$key], 2) }}</td>
                                </tr>
                            @endif
                        @endforeach

                        @if(isset($pd['credit']) && is_numeric($pd['credit']))
                            <tr class="text-base-content/70">
                                <td>Store credit used
                                    <span class="text-xs block opacity-70">not new money</span>
                                </td>
                                <td class="text-right tabular-nums">₦{{ number_format((float) $pd['credit'], 2) }}</td>
                            </tr>
                        @endif
                        @if(isset($pd['change_given']) && is_numeric($pd['change_given']))
                            <tr class="text-base-content/70">
                                <td>Change given</td>
                                <td class="text-right tabular-nums">−₦{{ number_format((float) $pd['change_given'], 2) }}</td>
                            </tr>
                        @endif
                        @if(isset($pd['stored_credit']) && is_numeric($pd['stored_credit']))
                            <tr class="text-base-content/70">
                                <td>Kept as store credit</td>
                                <td class="text-right tabular-nums">₦{{ number_format((float) $pd['stored_credit'], 2) }}</td>
                            </tr>
                        @endif
                        @if(isset($pd['shortfall']) && is_numeric($pd['shortfall']))
                            <tr class="text-error">
                                <td>Unpaid — became debt</td>
                                <td class="text-right tabular-nums">₦{{ number_format((float) $pd['shortfall'], 2) }}</td>
                            </tr>
                        @endif
                        @if(!empty($pd['coupon_code']))
                            <tr class="text-base-content/70">
                                <td>Coupon</td>
                                <td class="text-right font-mono text-sm">{{ $pd['coupon_code'] }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>

                @if(!empty($pd['debts_cleared']) && is_array($pd['debts_cleared']))
                    <div class="mt-2 text-xs text-base-content/60">
                        Overpayment cleared older debt:
                        @foreach($pd['debts_cleared'] as $d)
                            <span class="font-mono">{{ $d['invoice'] ?? '—' }}</span>
                            (₦{{ number_format((float) ($d['amount'] ?? 0), 2) }}){{ !$loop->last ? ',' : '' }}
                        @endforeach
                    </div>
                @endif
            @else
                <div class="flex items-start gap-2 p-2 bg-warning/10 rounded-lg text-xs">
                    <x-icon name="o-exclamation-triangle" class="w-4 h-4 shrink-0 mt-0.5 text-warning" />
                    <span>
                        No payment breakdown was recorded for this sale. It predates the till
                        storing how money was taken, so it appears under
                        <strong>Method not recorded</strong>.
                    </span>
                </div>
            @endif

            @if($viewSale->note)
                <div class="mt-4 text-sm text-base-content/60">Note: {{ $viewSale->note }}</div>
            @endif
        @endif

        <x-slot:actions>
            <x-button label="Close" wire:click="closeSale" class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-drawer>

</div>
