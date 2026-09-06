<div>
    <x-header title="Delivery Report" subtitle="What went out, where, and what it earned">
        <x-slot:actions>
            <x-button label="Export CSV" wire:click="export" icon="o-arrow-down-tray" class="btn-ghost btn-sm" spinner="export" />
        </x-slot:actions>
    </x-header>

    <x-card class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-input label="From" wire:model.live="dateFrom" type="date" />
            <x-input label="To" wire:model.live="dateTo" type="date" />
            <x-select label="Area" wire:model.live="area"
                      :options="collect($areas)->map(fn($a) => ['id' => $a, 'name' => $a])
                                  ->prepend(['id' => 'all', 'name' => 'All areas'])->values()"
                      option-value="id" option-label="name" />
        </div>
    </x-card>

    @if($totalOrders === 0)
        <x-card>
            <div class="text-center py-10">
                <x-icon name="o-truck" class="w-10 h-10 mx-auto text-base-content/20" />
                <p class="font-semibold mt-3">No deliveries in this window</p>
                <p class="text-sm text-base-content/60 mt-1">Try a wider date range.</p>
            </div>
        </x-card>
    @else
        {{-- Earned and still out are kept apart on purpose. A run that has not
             arrived has not earned its fee, and adding the two would report
             money the pharmacy might never see. --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            <div class="card bg-base-100 border border-base-200 p-4">
                <p class="text-xs text-base-content/60">Delivered</p>
                <p class="text-2xl font-bold">{{ number_format($deliveredCount) }}</p>
                <p class="text-xs text-base-content/50 mt-1">of {{ number_format($totalOrders) }} ordered</p>
            </div>

            <div class="card bg-success/5 border border-success/20 p-4">
                <p class="text-xs text-base-content/60">Fees earned</p>
                <p class="text-2xl font-bold text-success">₦{{ number_format($earned, 2) }}</p>
                <p class="text-xs text-base-content/50 mt-1">on deliveries that arrived</p>
            </div>

            <div class="card bg-warning/5 border border-warning/20 p-4">
                <p class="text-xs text-base-content/60">Still out</p>
                <p class="text-2xl font-bold text-warning">{{ number_format($stillOutCount) }}</p>
                <p class="text-xs text-base-content/50 mt-1">₦{{ number_format($stillOutFees, 2) }} riding on them</p>
            </div>

            <div class="card bg-base-100 border border-base-200 p-4">
                <p class="text-xs text-base-content/60">Not yet sent</p>
                <p class="text-2xl font-bold">{{ number_format($inHandCount) }}</p>
                <p class="text-xs text-base-content/50 mt-1">
                    @if($cancelledCount > 0)
                        {{ number_format($cancelledCount) }} cancelled, not counted
                    @else
                        still in the pharmacy
                    @endif
                </p>
            </div>
        </div>

        {{-- Earned is not the same as collected. A pay-on-delivery order that
             arrived has earned its fee; the cash exists once the rider hands
             it in. --}}
        @if($earned > $earnedPaid)
            <div class="alert alert-warning py-2 text-sm mb-6">
                <x-icon name="o-banknotes" class="w-4 h-4 shrink-0" />
                <span>
                    ₦{{ number_format($earned - $earnedPaid, 2) }} of the fees earned sits on orders
                    still marked unpaid — pay-on-delivery money that has not been reconciled at the till.
                </span>
            </div>
        @endif

        @if($freeDeliveries > 0)
            <p class="text-xs text-base-content/60 mb-6">
                {{ $freeDeliveries }} of the deliveries that arrived were free —
                either an area priced at nothing or a basket over the free-delivery threshold.
            </p>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div>
                <h3 class="font-semibold mb-3">By area</h3>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Area</th>
                                <th class="text-right">Sent</th>
                                <th class="text-right">Arrived</th>
                                <th class="text-right">Goods</th>
                                <th class="text-right">Fees earned</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($byArea as $row)
                                <tr>
                                    <td class="font-medium">{{ $row['area'] }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($row['orders']) }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($row['delivered']) }}</td>
                                    <td class="text-right tabular-nums">₦{{ number_format($row['goods'], 0) }}</td>
                                    <td class="text-right tabular-nums font-semibold">₦{{ number_format($row['fees'], 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h3 class="font-semibold mb-3">By rider</h3>

                @if($byRider->isEmpty())
                    <p class="text-sm text-base-content/60">
                        Nothing dispatched in this window.
                    </p>
                @else
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Carried by</th>
                                    <th class="text-right">Taken</th>
                                    <th class="text-right">Arrived</th>
                                    <th class="text-right">Still out</th>
                                    <th class="text-right">Fees</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($byRider as $row)
                                    <tr>
                                        <td class="font-medium">{{ $row['rider'] }}</td>
                                        <td class="text-right tabular-nums">{{ number_format($row['carried']) }}</td>
                                        <td class="text-right tabular-nums">{{ number_format($row['delivered']) }}</td>
                                        <td class="text-right tabular-nums">
                                            @if($row['still_out'] > 0)
                                                <span class="text-warning font-semibold">{{ number_format($row['still_out']) }}</span>
                                            @else
                                                0
                                            @endif
                                        </td>
                                        <td class="text-right tabular-nums">₦{{ number_format($row['fees'], 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Said here rather than left to be discovered when two
                         spellings of the same person are paid separately. --}}
                    <p class="text-xs text-base-content/50 mt-2">
                        A rider is a name typed in at dispatch, so this list is only as
                        careful as the typing. Two spellings of one person show as two riders.
                    </p>
                @endif
            </div>
        </div>
    @endif
</div>
