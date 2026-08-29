<div x-data x-on:open-return-receipt.window="window.open($event.detail.url, '_blank')">
    <x-header title="Sales History" subtitle="All staff" size="text-xl" />

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row gap-2 mb-4">
        <x-input icon="o-magnifying-glass" placeholder="Search..." wire:model.live.debounce="search" clearable class="flex-1" />
        <x-select wire:model.live="period" :options="[
            ['id' => 'today',     'name' => 'Today'],
            ['id' => 'yesterday', 'name' => 'Yesterday'],
            ['id' => 'week',      'name' => 'This Week'],
            ['id' => 'month',     'name' => 'This Month'],
            ['id' => 'year',      'name' => 'This Year'],
            ['id' => 'all',       'name' => 'All Time'],
            ['id' => 'custom',    'name' => 'Custom Range'],
        ]" option-value="id" option-label="name" class="sm:w-40" />
    </div>
    @if($period === 'custom')
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <x-input type="date" wire:model.live="dateFrom" label="From" class="w-40" />
            <x-input type="date" wire:model.live="dateTo" label="To" class="w-40" />
        </div>
    @endif

    <!-- Tab Switcher -->
    <div role="tablist" class="tabs tabs-border mb-4">
        <button role="tab" wire:click="$set('tab','pos')"
            @class(['tab', 'tab-active' => $tab === 'pos'])>
            POS Sales
        </button>
        <button role="tab" wire:click="$set('tab','handover')"
            @class(['tab', 'tab-active' => $tab === 'handover'])>
            Awaiting Handover
            @if($pendingHandoverCount > 0)
                <span class="badge badge-error badge-xs ml-1">{{ $pendingHandoverCount }}</span>
            @endif
        </button>
        @if(array_intersect(auth()->user()->role ?? [],['admin', 'pharmacist', 'branch_manager', 'sales']))
        <button role="tab" wire:click="$set('tab','online')"
            @class(['tab', 'tab-active' => $tab === 'online'])>
            Online Orders
            @if($pendingOnlineCount > 0)
                <span class="badge badge-warning badge-xs ml-1">{{ $pendingOnlineCount }}</span>
            @endif
        </button>
        @endif
        <button role="tab" wire:click="$set('tab','returns')"
            @class(['tab', 'tab-active' => $tab === 'returns'])>
            Returns
            @if($returnsCount > 0)
                <span class="badge badge-ghost badge-xs ml-1">{{ $returnsCount }}</span>
            @endif
        </button>
    </div>

    @if($tab === 'pos')
        <!-- POS Summary Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3 mb-4">
            <x-stat
                title="Revenue"
                value="₦{{ number_format($totalRevenue, 2) }}"
                description="billed · {{ $totalTransactions }} sales"
                icon="o-banknotes"
                color="text-primary"
            />
            {{-- What the drawer should actually hold. --}}
            <x-stat
                title="Cash Collected"
                value="₦{{ number_format($cashCollected, 2) }}"
                description="in the drawer"
                icon="o-wallet"
                color="text-success"
            />
            @if(array_intersect(auth()->user()->role ?? [],['admin', 'pharmacist', 'branch_manager']))
            <x-stat
                title="Profit"
                value="₦{{ number_format($totalProfit, 2) }}"
                description="{{ $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 1) : 0 }}% margin"
                icon="o-arrow-trending-up"
                color="{{ $totalProfit >= 0 ? 'text-success' : 'text-error' }}"
            />
            @endif
            <x-stat
                title="Items Sold"
                value="{{ number_format($totalItemsSold) }}"
                description="Avg ₦{{ number_format($avgSale, 2) }}/sale"
                icon="o-shopping-bag"
                color="text-info"
            />
            <div class="bg-base-100 rounded-lg p-4 shadow-sm">
                <div class="text-sm text-base-content/60 mb-2">Money Taken</div>
                {{-- Amounts actually tendered, not the billed total. --}}
                @foreach(['cash' => 'Cash', 'card' => 'Card', 'transfer' => 'Transfer'] as $key => $label)
                    <div class="flex justify-between items-center text-sm mb-1">
                        <x-badge :value="$label" @class([
                            'badge-xs',
                            'badge-success' => $key === 'cash',
                            'badge-info'    => $key === 'transfer',
                            'badge-primary' => $key === 'card',
                        ]) />
                        <span class="font-semibold tabular-nums">₦{{ number_format($collected[$key], 2) }}</span>
                    </div>
                @endforeach

                @if($cashRefunded > 0)
                    {{-- Already taken off the cash line above. Shown so the
                         figure is explained rather than just smaller. --}}
                    <div class="flex justify-between items-center text-xs text-error mt-1">
                        <span>Less cash refunded</span>
                        <span class="tabular-nums">−₦{{ number_format($cashRefunded, 2) }}</span>
                    </div>
                @endif

                @if($creditUsed > 0)
                    <div class="flex justify-between items-center text-xs text-base-content/50 mt-1">
                        <span>Store credit</span>
                        <span class="tabular-nums">₦{{ number_format($creditUsed, 2) }}</span>
                    </div>
                @endif

                @if($unrecordedCash > 0)
                    <div class="flex justify-between items-center text-xs text-warning mt-1">
                        <span>Method not recorded</span>
                        <span class="tabular-nums">₦{{ number_format($unrecordedCash, 2) }}</span>
                    </div>
                @endif
            </div>
        </div>

        <!-- POS Sales Table -->
        <x-table :headers="$posHeaders" :rows="$sales" with-pagination>
            @scope('cell_created_at', $sale)
                {{ $sale->created_at->format('M d, Y H:i') }}
            @endscope

            @scope('cell_customer.name', $sale)
                {{ $sale->customer?->name ?? 'Walk-in' }}
            @endscope

            @scope('cell_total_amount', $sale)
                ₦{{ number_format($sale->total_amount, 2) }}
            @endscope

            @scope('cell_payment_method', $sale)
                <x-badge :value="ucfirst($sale->payment_method)" @class([
                    'badge-success' => $sale->payment_method === 'cash',
                    'badge-info' => $sale->payment_method === 'transfer',
                    'badge-primary' => $sale->payment_method === 'card',
                ]) />
            @endscope

            @scope('cell_status', $sale)
                <x-badge :value="ucfirst($sale->status)" @class([
                    'badge-success' => $sale->status === 'completed',
                    'badge-warning' => $sale->status === 'pending',
                    'badge-error' => $sale->status === 'cancelled',
                ]) />
            @endscope

            @scope('actions', $sale, $returnWindowHours)
                {{-- @scope does not inherit view variables — resolve the role here. --}}
                @php $canRefund = (bool) array_intersect(auth()->user()->role ?? [], ['admin', 'branch_manager']); @endphp
                <div class="flex gap-1">
                    <x-button icon="o-eye" wire:click="viewDetails({{ $sale->id }})" class="btn-xs btn-ghost" tooltip="Details" />
                    <x-button icon="o-printer" link="{{ route('invoice.show', $sale->id) }}" class="btn-xs btn-ghost" tooltip="Invoice" external />
                    @if($canRefund && in_array($sale->status, ['completed', 'paid']) && $sale->created_at->diffInHours(now()) <= $returnWindowHours)
                        <x-button icon="o-arrow-uturn-left" wire:click="openReturn({{ $sale->id }})" class="btn-xs btn-ghost text-warning" tooltip="Return items" />
                    @endif
                </div>
            @endscope
        </x-table>

    @elseif($tab === 'handover')
        <!-- Awaiting Handover Summary -->
        <div class="grid grid-cols-2 gap-3 mb-4">
            <x-stat
                title="Awaiting Handover"
                value="{{ $pendingHandoverCount }}"
                description="Paid but not yet given to customer"
                icon="o-hand-raised"
                color="{{ $pendingHandoverCount > 0 ? 'text-error' : 'text-base-content/40' }}"
            />
            <x-stat
                title="Total Value"
                value="₦{{ number_format($pendingHandoverTotal, 2) }}"
                description="Across all pending handovers"
                icon="o-banknotes"
                color="text-warning"
            />
        </div>

        @if($pendingHandoverCount === 0)
            <div class="text-center py-10 text-base-content/50">
                <x-icon name="o-check-circle" class="w-12 h-12 mx-auto mb-2 text-success" />
                <div class="font-semibold">All clear — no pending handovers.</div>
            </div>
        @else
        <x-table :headers="$posHeaders" :rows="$handoverSales" with-pagination>
            @scope('cell_created_at', $sale)
                {{ $sale->created_at->format('M d, Y H:i') }}
            @endscope

            @scope('cell_customer.name', $sale)
                {{ $sale->customer?->name ?? 'Walk-in' }}
            @endscope

            @scope('cell_total_amount', $sale)
                ₦{{ number_format($sale->total_amount, 2) }}
            @endscope

            @scope('cell_payment_method', $sale)
                <x-badge :value="ucfirst($sale->payment_method)" @class([
                    'badge-success' => $sale->payment_method === 'cash',
                    'badge-info'    => $sale->payment_method === 'transfer',
                    'badge-primary' => $sale->payment_method === 'card',
                ]) />
            @endscope

            @scope('cell_status', $sale)
                <x-badge value="Paid – Awaiting Handover" class="badge-warning" />
            @endscope

            @scope('actions', $sale)
                @php $canHandOver = (bool) array_intersect(auth()->user()->role ?? [], ['admin', 'branch_manager']); @endphp
                <div class="flex gap-1">
                    <x-button icon="o-eye" wire:click="viewDetails({{ $sale->id }})" class="btn-xs btn-ghost" tooltip="View details" />
                    @if($canHandOver)
                        <x-button
                            label="Hand Over"
                            icon="o-check"
                            wire:click="completeHandover({{ $sale->id }})"
                            wire:confirm="Mark this sale as handed over to the customer?"
                            class="btn-xs btn-success"
                        />
                    @endif
                </div>
            @endscope
        </x-table>
        @endif

    @elseif($tab === 'online')
        <!-- Online Orders Summary Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3 mb-4">
            <x-stat
                title="Revenue"
                value="₦{{ number_format($onlineRevenue, 2) }}"
                description="{{ $onlineTransactions }} completed orders"
                icon="o-globe-alt"
                color="text-secondary"
            />
            <x-stat
                title="Pending"
                value="{{ $pendingOnlineCount }}"
                description="Awaiting processing"
                icon="o-clock"
                color="{{ $pendingOnlineCount > 0 ? 'text-warning' : 'text-base-content/40' }}"
            />
            <x-stat
                title="Avg Order"
                value="₦{{ number_format($onlineTransactions > 0 ? $onlineRevenue / $onlineTransactions : 0, 2) }}"
                description="Per completed order"
                icon="o-shopping-cart"
                color="text-info"
            />
            <div class="bg-base-100 rounded-lg p-4 shadow-sm">
                <div class="text-sm text-base-content/60 mb-2">Payment Methods</div>
                @forelse($onlinePaymentBreakdown as $method)
                    <div class="flex justify-between items-center text-sm mb-1">
                        <span class="flex items-center gap-2">
                            <x-badge :value="ucfirst(str_replace('_', ' ', $method->payment_method))" @class([
                                'badge-xs',
                                'badge-primary' => $method->payment_method === 'paystack',
                                'badge-success' => $method->payment_method === 'pay_on_delivery',
                            ]) />
                            <span class="text-base-content/60">{{ $method->count }}×</span>
                        </span>
                        <span class="font-semibold">₦{{ number_format($method->total, 2) }}</span>
                    </div>
                @empty
                    <div class="text-sm text-base-content/40">No orders yet</div>
                @endforelse
            </div>
        </div>

        <!-- Online Orders Table -->
        <x-table :headers="$onlineHeaders" :rows="$onlineOrders" with-pagination>
            @scope('cell_created_at', $order)
                {{ $order->created_at->format('M d, Y H:i') }}
            @endscope

            @scope('cell_customer_display', $order)
                {{ $order->customer?->name ?? $order->guest_name ?? 'Guest' }}
                @if(!$order->customer_id)
                    <span class="text-xs text-base-content/50 ml-1">(guest)</span>
                @endif
            @endscope

            @scope('cell_total_amount', $order)
                ₦{{ number_format($order->total_amount, 2) }}
            @endscope

            @scope('cell_payment_method', $order)
                <x-badge :value="ucfirst(str_replace('_', ' ', $order->payment_method))" @class([
                    'badge-primary' => $order->payment_method === 'paystack',
                    'badge-success' => $order->payment_method === 'pay_on_delivery',
                ]) />
            @endscope

            @scope('cell_status', $order)
                <x-badge :value="ucfirst($order->status)" @class([
                    'badge-warning'  => in_array($order->status, ['pending', 'processing']),
                    'badge-info'     => $order->status === 'ready',
                    'badge-success'  => $order->status === 'completed',
                    'badge-error'    => $order->status === 'cancelled',
                ]) />
            @endscope

            @scope('actions', $order)
                <x-button icon="o-eye" wire:click="viewOrderDetails({{ $order->id }})" class="btn-xs btn-ghost" tooltip="Details" />
            @endscope
        </x-table>

    @elseif($tab === 'returns')
        {{-- The same page the whole pharmacy can reach, rendered here so there
             is one implementation of what came back rather than two. --}}
        <livewire:sales.returns />

    @endif

    <!-- Return Modal -->
    <x-modal wire:model="returnModal" title="Process Return" class="backdrop-blur">
        @if($returnableSale)
            <div class="space-y-3">
                @if($returnError)
                    <div role="alert" class="alert alert-error">
                        <x-icon name="o-exclamation-circle" class="w-5 h-5 shrink-0" />
                        <span>{{ $returnError }}</span>
                    </div>
                @endif

                <div class="text-sm text-base-content/60">
                    Sale #{{ $returnableSale->id }} &mdash; {{ $returnableSale->created_at->format('M d, Y H:i') }}
                    @if($returnableSale->customer)
                        &mdash; {{ $returnableSale->customer->name }}
                    @endif
                </div>

                @foreach($returnableSale->saleItems as $item)
                    @php $maxQty = $returnableQtys[$item->id] ?? 0; @endphp
                    <div @class(['flex items-center justify-between gap-3 p-3 bg-base-200 rounded-lg', 'opacity-40' => $maxQty == 0])>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-sm truncate">{{ $item->product->name }}</div>
                            <div class="text-xs text-base-content/60">
                                ₦{{ number_format($item->unit_price, 2) }} &times; {{ $item->quantity }}
                                @if($maxQty < $item->quantity)
                                    <span class="text-warning ml-1">({{ $maxQty }} returnable)</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-xs text-base-content/60">Qty:</span>
                            <input
                                type="number"
                                wire:model.live="returnQtys.{{ $item->id }}"
                                min="0"
                                max="{{ $maxQty }}"
                                class="input input-bordered input-sm w-16 text-center"
                                {{ $maxQty == 0 ? 'disabled' : '' }}
                            />
                            <span class="text-xs text-base-content/60">/ {{ $maxQty }}</span>
                        </div>
                    </div>
                @endforeach

                <x-textarea wire:model="returnReason" label="Reason (optional)" placeholder="Describe why items are being returned..." rows="2" />

                @php
                    $liveTotal = 0.0;
                    foreach ($returnableSale->saleItems as $item) {
                        $liveTotal += ((int) ($returnQtys[$item->id] ?? 0)) * (float) $item->unit_price;
                    }
                @endphp
                @if($liveTotal > 0)
                    {{-- How the money goes back. A walk-in has no account for
                         store credit to sit on, so there is nothing to choose. --}}
                    @if($returnableSale->customer_id)
                        <div class="rounded-lg border border-base-300 p-3">
                            <p class="text-sm font-semibold mb-2">Refund as</p>
                            <div class="flex flex-col sm:flex-row gap-2">
                                <label class="flex items-start gap-2 flex-1 cursor-pointer rounded-lg border p-2.5
                                              {{ $refundMethod === 'credit' ? 'border-primary bg-primary/5' : 'border-base-300' }}">
                                    <input type="radio" class="radio radio-sm mt-0.5" value="credit" wire:model.live="refundMethod" />
                                    <span>
                                        <span class="block text-sm font-medium">Store credit</span>
                                        <span class="block text-xs text-base-content/60">Goes on their account for next time.</span>
                                    </span>
                                </label>
                                <label class="flex items-start gap-2 flex-1 cursor-pointer rounded-lg border p-2.5
                                              {{ $refundMethod === 'cash' ? 'border-primary bg-primary/5' : 'border-base-300' }}">
                                    <input type="radio" class="radio radio-sm mt-0.5" value="cash" wire:model.live="refundMethod" />
                                    <span>
                                        <span class="block text-sm font-medium">Cash</span>
                                        <span class="block text-xs text-base-content/60">Out of the till, now.</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    @else
                        <div class="rounded-lg border border-base-300 bg-base-200/40 p-3 text-sm">
                            <span class="font-semibold">Refunded in cash.</span>
                            <span class="text-base-content/70">
                                This sale has no customer attached, so there is no account to credit.
                            </span>
                        </div>
                    @endif

                    <div class="flex justify-between items-center p-3 bg-success/10 rounded-lg border border-success/20">
                        <span class="text-sm font-semibold text-success">
                            {{ $refundMethod === 'cash' ? 'Cash to give back:' : 'Credit to be issued:' }}
                        </span>
                        <span class="text-lg font-bold text-success">₦{{ number_format($liveTotal, 2) }}</span>
                    </div>
                @endif
            </div>

            <x-slot:actions>
                <x-button label="Cancel" wire:click="$set('returnModal', false)" class="btn-ghost" />
                <x-button
                    label="Process Return"
                    wire:click="processReturn"
                    class="btn-error"
                    icon="o-arrow-uturn-left"
                    spinner="processReturn"
                    :disabled="$liveTotal == 0"
                />
            </x-slot:actions>
        @endif
    </x-modal>

    <!-- Sale / Order Details Drawer -->
    <x-drawer wire:model="detailsDrawer" title="{{ $tab === 'online' ? 'Order ' . $viewOrder?->order_number : 'Sale #' . $viewSale?->id }}" right class="w-96 lg:w-1/3">
        @if(in_array($tab, ['pos', 'handover']) && $viewSale)
            <div class="space-y-2 mb-4">
                <div class="flex justify-between"><span class="text-base-content/60">Date:</span> <span>{{ $viewSale->created_at->format('M d, Y H:i') }}</span></div>
                <div class="flex justify-between"><span class="text-base-content/60">Cashier:</span> <span>{{ $viewSale->user->name }}</span></div>
                <div class="flex justify-between"><span class="text-base-content/60">Customer:</span> <span>{{ $viewSale->customer?->name ?? 'Walk-in' }}</span></div>
                <div class="flex justify-between"><span class="text-base-content/60">Payment:</span> <span>{{ ucfirst($viewSale->payment_method) }}</span></div>
                @if($viewSale->payment_method === 'split' && $viewSale->payment_details)
                    <div class="bg-base-200 rounded p-2 mt-1">
                        @foreach($viewSale->payment_details as $method => $amount)
                            <div class="flex justify-between text-sm"><span class="text-base-content/60">{{ ucfirst($method) }}:</span> <span>₦{{ number_format($amount, 2) }}</span></div>
                        @endforeach
                    </div>
                @endif
            </div>

            <x-hr />

            <div class="space-y-2">
                @php $saleProfit = 0; @endphp
                @foreach($viewSale->saleItems as $item)
                    @php $saleProfit += ($item->unit_price - $item->cost_price) * $item->quantity; @endphp
                    <div class="flex justify-between items-center p-2 bg-base-200 rounded">
                        <div>
                            <div class="font-semibold text-sm">{{ $item->product->name }}</div>
                            <div class="text-xs text-base-content/60">Batch: {{ $item->batch->batch_number }} | Qty: {{ $item->quantity }} × ₦{{ number_format($item->unit_price, 2) }}</div>
                            @if(array_intersect(auth()->user()->role ?? [],['admin', 'pharmacist', 'branch_manager']))
                                <div class="text-xs text-success">Profit: ₦{{ number_format(($item->unit_price - $item->cost_price) * $item->quantity, 2) }}</div>
                            @endif
                        </div>
                        <div class="font-bold">₦{{ number_format($item->subtotal, 2) }}</div>
                    </div>
                @endforeach
            </div>

            <x-hr />

            <div class="space-y-1">
                <div class="flex justify-between text-lg font-bold">
                    <span>Total</span>
                    <span class="text-primary">₦{{ number_format($viewSale->total_amount, 2) }}</span>
                </div>
                @if(array_intersect(auth()->user()->role ?? [],['admin', 'pharmacist', 'branch_manager']))
                <div class="flex justify-between text-sm">
                    <span class="text-base-content/60">Profit</span>
                    <span class="text-success font-semibold">₦{{ number_format($saleProfit, 2) }}</span>
                </div>
                @endif
            </div>

            @if($viewSale->note)
                <div class="mt-3 text-sm text-base-content/60">Note: {{ $viewSale->note }}</div>
            @endif

            <div class="mt-4">
                <x-button label="Print Invoice" link="{{ route('invoice.show', $viewSale->id) }}" class="btn-primary btn-block" icon="o-printer" external />
            </div>

            {{-- HiFastLink Wi-Fi access tied to this receipt --}}
            @if($viewSale->voucher_redeemed_at)
                <div class="mt-4 p-3 rounded-lg bg-base-200/60 text-sm space-y-2">
                    <div class="flex items-center gap-2 font-semibold">
                        <x-icon name="o-wifi" class="w-4 h-4" /> Wi-Fi Access
                    </div>
                    @if($viewSale->voucher_revoked_at)
                        <div class="text-error">Revoked {{ $viewSale->voucher_revoked_at->diffForHumans() }}.</div>
                    @elseif($viewSale->wifiActive())
                        <div class="text-success">Active until {{ $viewSale->wifiExpiresAt()->format('D, d M Y h:i A') }}.</div>
                        @if($this->isElevated())
                            <x-button label="Revoke Wi-Fi access"
                                wire:click="revokeWifi({{ $viewSale->id }})"
                                wire:confirm="Revoke internet access for this receipt? The device will no longer be able to reconnect."
                                class="btn-error btn-outline btn-block btn-sm" icon="o-no-symbol"
                                spinner="revokeWifi" />
                        @endif
                    @else
                        <div class="text-base-content/60">Expired {{ $viewSale->wifiExpiresAt()?->diffForHumans() }}.</div>
                    @endif
                </div>
            @endif

        @elseif($tab === 'online' && $viewOrder)
            <div class="space-y-2 mb-4">
                <div class="flex justify-between"><span class="text-base-content/60">Date:</span> <span>{{ $viewOrder->created_at->format('M d, Y H:i') }}</span></div>
                <div class="flex justify-between">
                    <span class="text-base-content/60">Customer:</span>
                    <span>{{ $viewOrder->customer?->name ?? $viewOrder->guest_name ?? 'Guest' }}</span>
                </div>
                @if($viewOrder->guest_phone)
                    <div class="flex justify-between"><span class="text-base-content/60">Phone:</span> <span>{{ $viewOrder->guest_phone }}</span></div>
                @endif
                <div class="flex justify-between">
                    <span class="text-base-content/60">Payment:</span>
                    <span>{{ ucfirst(str_replace('_', ' ', $viewOrder->payment_method)) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-base-content/60">Fulfillment:</span>
                    <span>{{ ucfirst($viewOrder->fulfillment_type) }}</span>
                </div>
                @if($viewOrder->delivery_address)
                    <div class="flex justify-between"><span class="text-base-content/60">Address:</span> <span class="text-right text-sm max-w-[60%]">{{ $viewOrder->delivery_address }}</span></div>
                @endif
                <div class="flex justify-between">
                    <span class="text-base-content/60">Status:</span>
                    <x-badge :value="ucfirst($viewOrder->status)" @class([
                        'badge-warning'  => in_array($viewOrder->status, ['pending', 'processing']),
                        'badge-info'     => $viewOrder->status === 'ready',
                        'badge-success'  => $viewOrder->status === 'completed',
                        'badge-error'    => $viewOrder->status === 'cancelled',
                    ]) />
                </div>
                @if($viewOrder->claimedByUser)
                    <div class="flex justify-between"><span class="text-base-content/60">Processed by:</span> <span>{{ $viewOrder->claimedByUser->name }}</span></div>
                @endif
            </div>

            <x-hr />

            <div class="space-y-2">
                @foreach($viewOrder->items as $item)
                    <div class="flex justify-between items-center p-2 bg-base-200 rounded">
                        <div>
                            <div class="font-semibold text-sm">{{ $item->product->name }}</div>
                            <div class="text-xs text-base-content/60">Qty: {{ $item->quantity }} × ₦{{ number_format($item->unit_price, 2) }}</div>
                        </div>
                        <div class="font-bold">₦{{ number_format($item->subtotal, 2) }}</div>
                    </div>
                @endforeach
            </div>

            <x-hr />

            <div class="space-y-1">
                <div class="flex justify-between text-sm">
                    <span class="text-base-content/60">Subtotal</span>
                    <span>₦{{ number_format($viewOrder->subtotal, 2) }}</span>
                </div>
                @if($viewOrder->delivery_fee > 0)
                    <div class="flex justify-between text-sm">
                        <span class="text-base-content/60">Delivery Fee</span>
                        <span>₦{{ number_format($viewOrder->delivery_fee, 2) }}</span>
                    </div>
                @endif
                <div class="flex justify-between text-lg font-bold">
                    <span>Total</span>
                    <span class="text-secondary">₦{{ number_format($viewOrder->total_amount, 2) }}</span>
                </div>
            </div>

            @if($viewOrder->note)
                <div class="mt-3 text-sm text-base-content/60">Note: {{ $viewOrder->note }}</div>
            @endif
        @endif
    </x-drawer>
</div>
