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
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-5">

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
    </div>

    {{-- Every sale behind the numbers --}}
    <x-card title="Every sale in this period" subtitle="The figures above come from these">
        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Sold by</th>
                        <th class="text-right">Sold for</th>
                        <th class="text-right">Cost</th>
                        <th class="text-right">Profit</th>
                        <th class="text-right">Margin</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales as $sale)
                        @php
                            $net    = (float) $sale->total_amount - (float) ($sale->coupon_discount ?? 0);
                            $cost   = (float) ($sale->cogs ?? 0);
                            $profit = $net - $cost;
                        @endphp
                        <tr class="hover">
                            <td class="font-mono text-xs">{{ $sale->invoice_number ?? '#' . $sale->id }}</td>
                            <td class="text-xs whitespace-nowrap">{{ $sale->created_at->format('d M H:i') }}</td>
                            <td class="text-sm">{{ $sale->customer?->name ?? 'Walk-in' }}</td>
                            <td class="text-sm">{{ $sale->user?->name ?? '—' }}</td>
                            <td class="text-right tabular-nums">₦{{ number_format($net, 2) }}</td>
                            <td class="text-right tabular-nums text-base-content/60">₦{{ number_format($cost, 2) }}</td>
                            <td class="text-right tabular-nums font-semibold {{ $profit >= 0 ? 'text-success' : 'text-error' }}">
                                ₦{{ number_format($profit, 2) }}
                            </td>
                            <td class="text-right tabular-nums text-xs text-base-content/60">
                                {{ $net > 0 ? number_format(($profit / $net) * 100, 1) . '%' : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-base-content/40 py-10">
                                No settled sales in this period.
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
</div>
