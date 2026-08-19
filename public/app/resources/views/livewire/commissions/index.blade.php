<div>
    @if($isManager)
        {{-- ── Admin / Branch Manager view ── --}}
        <x-header title="Commissions" subtitle="Referral earnings and payouts">
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-base-content/60 whitespace-nowrap">Activity for</span>
                    <input type="date" wire:model.live="date"
                           class="input input-bordered input-sm" max="{{ today()->format('Y-m-d') }}" />
                </div>
            </x-slot:actions>
        </x-header>

        <div class="grid grid-cols-2 gap-4 mb-6">
            <x-stat title="Total Pending" value="₦{{ number_format($overallPending, 2) }}"
                icon="o-clock" color="{{ $overallPending > 0 ? 'text-warning' : 'text-base-content/40' }}" />
            <x-stat title="Total Paid Out" value="₦{{ number_format($overallPaid, 2) }}"
                icon="o-check-circle" color="text-success" />
        </div>

        <div class="bg-base-100 rounded-xl shadow-sm overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th>Role</th>
                        <th class="text-center">Target<br><span class="font-normal text-xs text-base-content/50">{{ \Carbon\Carbon::parse($date)->format('d M') }}</span></th>
                        <th class="text-center">Customers</th>
                        <th class="text-right">Total Earned</th>
                        <th class="text-right">Paid</th>
                        <th class="text-right">Pending</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($allEligible as $item)
                        <tr class="hover">
                            <td>
                                <button wire:click="openDetail({{ $item->id }})"
                                    class="font-semibold hover:text-primary text-left">
                                    {{ $item->name }}
                                </button>
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @foreach($item->role as $r)
                                        @if(in_array($r, ['promoter', 'cashier', 'sales']))
                                            <x-badge :value="ucfirst($r)" class="badge-xs badge-ghost" />
                                        @endif
                                    @endforeach
                                </div>
                            </td>
                            <td class="text-center">
                                @if($item->progress)
                                    <span class="font-semibold tabular-nums {{ $item->progress['redeemed'] >= $item->progress['target'] ? 'text-success' : '' }}">
                                        {{ $item->progress['redeemed'] }}<span class="text-base-content/40">/{{ $item->progress['target'] }}</span>
                                    </span>
                                    @if($item->progress['stalled'] > 0)
                                        <div class="text-xs text-warning">{{ $item->progress['stalled'] }} not connected</div>
                                    @endif
                                @else
                                    <span class="text-base-content/30">—</span>
                                @endif
                            </td>
                            <td class="text-center">{{ $item->count }}</td>
                            <td class="text-right font-semibold">₦{{ number_format($item->total_earned, 2) }}</td>
                            <td class="text-right text-success">₦{{ number_format($item->total_paid, 2) }}</td>
                            <td class="text-right">
                                @if($item->pending > 0)
                                    <span class="font-bold text-warning">₦{{ number_format($item->pending, 2) }}</span>
                                @else
                                    <span class="text-base-content/40">—</span>
                                @endif
                            </td>
                            <td>
                                @if($item->pending > 0)
                                    <x-button label="Pay" wire:click="openPay({{ $item->id }})"
                                        class="btn-xs btn-success" icon="o-banknotes" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-base-content/40 py-10">
                                No commission-eligible staff yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Registered and given a code, but never connected --}}
        @if($stalledCodes->isNotEmpty())
            <x-card class="mt-6" title="Registered but never connected"
                    subtitle="{{ \Carbon\Carbon::parse($date)->format('d M Y') }} — credit any that were not the promoter's fault">
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Promoter</th>
                                <th>Code</th>
                                <th>Issued</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($stalledCodes as $code)
                                <tr class="hover">
                                    <td class="font-semibold">{{ $code->customer?->name ?? '—' }}</td>
                                    <td>{{ $code->promoter?->name ?? '—' }}</td>
                                    <td class="font-mono text-xs">{{ $code->code }}</td>
                                    <td class="text-xs text-base-content/60">{{ $code->created_at->format('H:i') }}</td>
                                    <td class="text-right">
                                        @if($code->already_paid)
                                            <span class="badge badge-success badge-sm">Credited</span>
                                        @else
                                            <x-button label="Credit" wire:click="creditCode({{ $code->id }})"
                                                wire:confirm="Pay {{ $code->promoter?->name }} for {{ $code->customer?->name }} even though they never connected?"
                                                class="btn-xs btn-outline" icon="o-banknotes" />
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endif

        {{-- Detail drawer: customers registered by a staff member --}}
        <x-drawer wire:model="detailDrawer" title="Customers registered by {{ $detailUser?->name }}" right class="w-96">
            @if($detailUser)
                <div class="space-y-2">
                    @forelse($detailCommissions as $commission)
                        <div class="flex justify-between items-center p-3 bg-base-200 rounded-lg">
                            <div>
                                <div class="font-semibold text-sm">{{ $commission->customer->name }}</div>
                                <div class="text-xs text-base-content/60">
                                    {{ $commission->created_at->format('M d, Y') }}
                                </div>
                            </div>
                            <div class="text-right shrink-0 ml-4">
                                <div class="font-bold text-sm">₦{{ number_format($commission->amount, 2) }}</div>
                                @if($commission->paid_at)
                                    <x-badge value="Paid" class="badge-xs badge-success" />
                                @else
                                    <x-badge value="Pending" class="badge-xs badge-warning" />
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-base-content/40 py-10">No customers registered yet.</div>
                    @endforelse
                </div>
            @endif
        </x-drawer>

        {{-- Pay modal --}}
        <x-modal wire:model="payModal" title="Mark Commission as Paid">
            @if($payUser)
                <div class="space-y-4">
                    <div class="text-center py-2">
                        <div class="text-4xl font-bold text-success mb-1">
                            ₦{{ number_format($payAmount, 2) }}
                        </div>
                        <div class="text-base-content/60">
                            pending for <span class="font-semibold text-base-content">{{ $payUser->name }}</span>
                        </div>
                    </div>
                    <div role="alert" class="alert">
                        <x-icon name="o-information-circle" class="w-5 h-5 shrink-0" />
                        <span class="text-sm">Make the bank transfer first, then click Mark Paid to record it here.</span>
                    </div>
                </div>
                <x-slot:actions>
                    <x-button label="Cancel" wire:click="$set('payModal', false)" class="btn-ghost" />
                    <x-button label="Mark Paid" wire:click="confirmPay" class="btn-success"
                        icon="o-check" spinner="confirmPay" />
                </x-slot:actions>
            @endif
        </x-modal>

    @else
        {{-- ── Staff own view (promoter / cashier / sales) ── --}}
        <x-header title="My Commissions" subtitle="Your referral earnings" />

        <div class="grid grid-cols-3 gap-4 mb-6">
            <x-stat title="Total Earned" value="₦{{ number_format($myTotal, 2) }}"
                icon="o-currency-dollar" color="text-primary" />
            <x-stat title="Paid Out" value="₦{{ number_format($myPaid, 2) }}"
                icon="o-check-circle" color="text-success" />
            <x-stat title="Pending" value="₦{{ number_format($myPending, 2) }}"
                icon="o-clock" color="{{ $myPending > 0 ? 'text-warning' : 'text-base-content/40' }}" />
        </div>

        <x-table :headers="[
            ['key' => 'customer_name', 'label' => 'Customer'],
            ['key' => 'created_at',    'label' => 'Registered On'],
            ['key' => 'amount',        'label' => 'Commission'],
            ['key' => 'status',        'label' => 'Status'],
        ]" :rows="$myCommissions" with-pagination>

            @scope('cell_customer_name', $commission)
                {{ $commission->customer->name }}
            @endscope

            @scope('cell_created_at', $commission)
                {{ $commission->created_at->format('M d, Y') }}
            @endscope

            @scope('cell_amount', $commission)
                <span class="font-bold text-primary">₦{{ number_format($commission->amount, 2) }}</span>
            @endscope

            @scope('cell_status', $commission)
                @if($commission->paid_at)
                    <x-badge value="Paid" class="badge-success" />
                @else
                    <x-badge value="Pending" class="badge-warning" />
                @endif
            @endscope
        </x-table>
    @endif
</div>
