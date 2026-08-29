<div>
    <x-header title="Cover Report" subtitle="Premiums in against medicine given out" size="text-xl" />

    <div class="flex flex-col sm:flex-row gap-2 mb-4">
        <x-input type="date" wire:model.live="from" class="sm:w-44" />
        <x-input type="date" wire:model.live="to" class="sm:w-44" />
        <div class="flex gap-1">
            <x-button label="This month" wire:click="thisMonth" class="btn-ghost btn-sm" />
            <x-button label="Last month" wire:click="lastMonth" class="btn-ghost btn-sm" />
            <x-button label="This year" wire:click="thisYear" class="btn-ghost btn-sm" />
        </div>
    </div>

    {{-- Two answers, both shown, because either alone misleads. --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <x-card>
            <div class="text-xs text-base-content/50 uppercase tracking-wide">Cash result</div>
            <div class="text-3xl font-bold tabular-nums {{ $cashResult >= 0 ? 'text-success' : 'text-error' }}">
                {{ $cashResult < 0 ? '−' : '' }}&#8358;{{ number_format(abs($cashResult), 2) }}
            </div>
            <p class="text-sm text-base-content/60 mt-1">
                Premiums collected, less what the medicine given out cost the pharmacy.
                This is the figure that says whether the scheme pays for itself.
            </p>
        </x-card>

        <x-card>
            <div class="text-xs text-base-content/50 uppercase tracking-wide">Against walking in and buying</div>
            <div class="text-3xl font-bold tabular-nums {{ $tradingResult >= 0 ? 'text-success' : 'text-warning' }}">
                {{ $tradingResult < 0 ? '−' : '' }}&#8358;{{ number_format(abs($tradingResult), 2) }}
            </div>
            <p class="text-sm text-base-content/60 mt-1">
                What the shop would have taken had these customers simply bought the same
                medicine. Weigh this against the custom the scheme brings in.
            </p>
        </x-card>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <x-card>
            <div class="text-xs text-base-content/50">Premiums in</div>
            <div class="text-xl font-bold tabular-nums text-success">&#8358;{{ number_format($premiums, 2) }}</div>
        </x-card>
        <x-card>
            <div class="text-xs text-base-content/50">Claims paid</div>
            <div class="text-xl font-bold tabular-nums">&#8358;{{ number_format($claimedValue, 2) }}</div>
            <div class="text-xs text-base-content/50">{{ number_format($claimCount) }} {{ Str::plural('visit', $claimCount) }}</div>
        </x-card>
        <x-card>
            <div class="text-xs text-base-content/50">Cost of those claims</div>
            <div class="text-xl font-bold tabular-nums">&#8358;{{ number_format($claimedCost, 2) }}</div>
        </x-card>
        <x-card>
            <div class="text-xs text-base-content/50">On cover now</div>
            <div class="text-xl font-bold tabular-nums">{{ number_format($liveCount) }}</div>
            <div class="text-xs text-base-content/50">
                up to &#8358;{{ number_format($exposure, 2) }} a month
            </div>
        </x-card>
    </div>

    @if($exposure > 0 && $premiums > 0 && $exposure > $premiums * 2)
        {{-- Worth saying out loud rather than leaving in a column: this is the
             month where a bad run would hurt. --}}
        <div class="alert alert-warning py-2 mb-4 text-sm gap-2">
            <x-icon name="o-exclamation-triangle" class="w-4 h-4 shrink-0" />
            <span>
                If everyone on cover claimed their full allowance this month, it would cost
                &#8358;{{ number_format($exposure, 2) }} against
                &#8358;{{ number_format($premiums, 2) }} collected. That does not usually happen,
                but the pharmacy carries it if it does.
            </span>
        </div>
    @endif

    {{-- Per plan, so a plan that loses money can be repriced rather than the
         whole scheme abandoned. --}}
    <x-card title="By plan" class="mb-4">
        @if($byPlan->isEmpty())
            <p class="text-sm text-base-content/60 py-4 text-center">Nothing on cover in this period.</p>
        @else
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th class="text-right">On it</th>
                            <th class="text-right">Premiums</th>
                            <th class="text-right">Claims</th>
                            <th class="text-right">Cost</th>
                            <th class="text-right">Cash result</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($byPlan as $row)
                            @php $result = $row['premiums'] - $row['cost']; @endphp
                            <tr>
                                <td>
                                    <div class="font-medium">{{ $row['plan']->name }}</div>
                                    <div class="text-xs text-base-content/50">{{ $row['plan']->summary() }}</div>
                                </td>
                                <td class="text-right tabular-nums">{{ $row['live'] }}</td>
                                <td class="text-right tabular-nums">&#8358;{{ number_format($row['premiums'], 2) }}</td>
                                <td class="text-right tabular-nums">&#8358;{{ number_format($row['value'], 2) }}</td>
                                <td class="text-right tabular-nums">&#8358;{{ number_format($row['cost'], 2) }}</td>
                                <td class="text-right tabular-nums font-bold {{ $result >= 0 ? 'text-success' : 'text-error' }}">
                                    {{ $result < 0 ? '−' : '' }}&#8358;{{ number_format(abs($result), 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <x-card title="Who is drawing most" subtitle="Normal in any scheme — a worry only if the pool stops covering it">
        @if($heaviest->isEmpty())
            <p class="text-sm text-base-content/60 py-4 text-center">No claims in this period.</p>
        @else
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th class="text-right">Visits</th>
                            <th class="text-right">Paid in</th>
                            <th class="text-right">Claimed</th>
                            <th class="text-right">Cost to us</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($heaviest as $row)
                            @php $in = (float) ($paidIn[$row->customer_id] ?? 0); @endphp
                            <tr>
                                <td class="truncate max-w-[14rem]">{{ $row->customer_name }}</td>
                                <td class="text-right tabular-nums">{{ $row->visits }}</td>
                                <td class="text-right tabular-nums">&#8358;{{ number_format($in, 2) }}</td>
                                <td class="text-right tabular-nums">&#8358;{{ number_format((float) $row->claimed, 2) }}</td>
                                <td class="text-right tabular-nums {{ (float) $row->cost > $in ? 'text-warning font-medium' : '' }}">
                                    &#8358;{{ number_format((float) $row->cost, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</div>
