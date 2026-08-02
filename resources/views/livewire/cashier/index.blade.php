<div wire:poll.5s>
    <x-header title="Cashier" subtitle="Process invoice payments" size="text-xl">
        <x-slot:middle class="!justify-end">
            <x-input icon="o-magnifying-glass" placeholder="Search invoice #..." wire:model.live.debounce="searchInvoice" clearable />
        </x-slot:middle>
    </x-header>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Pending Invoices -->
        <x-card title="Pending" subtitle="{{ $pendingInvoices->count() }} awaiting payment">
            @forelse($pendingInvoices as $invoice)
                <div class="flex justify-between items-center p-3 bg-base-200 rounded-lg mb-2 active:bg-base-300 transition-all">
                    <div class="min-w-0 flex-1">
                        <div class="font-bold text-sm">{{ $invoice->invoice_number }}</div>
                        <div class="text-xs text-base-content/60 truncate">{{ $invoice->customer?->name ?? 'Walk-in' }}</div>
                        <div class="text-xs text-base-content/60">{{ $invoice->user->name }} | {{ $invoice->created_at->format('H:i') }}</div>
                    </div>
                    <div class="text-right ml-3 shrink-0">
                        <div class="font-bold text-primary">₦{{ number_format($invoice->total_amount, 2) }}</div>
                        <x-button label="Pay" wire:click="openPayment({{ $invoice->id }})" class="btn-xs btn-primary mt-1" icon="o-banknotes" />
                    </div>
                </div>
            @empty
                <div class="text-center py-6 text-base-content/60">
                    <x-icon name="o-check-circle" class="w-10 h-10 mx-auto mb-2 opacity-30" />
                    <p class="text-sm">No pending invoices</p>
                </div>
            @endforelse
        </x-card>

        <!-- Recently Paid -->
        <x-card title="Recently Paid" subtitle="Awaiting handover">
            @forelse($recentPaid as $invoice)
                <div class="flex justify-between items-center p-2 border-b border-base-200 last:border-0">
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold text-sm">{{ $invoice->invoice_number }}</div>
                        <div class="text-xs text-base-content/60 truncate">{{ $invoice->customer?->name ?? 'Walk-in' }} | {{ ucfirst($invoice->payment_method) }}</div>
                    </div>
                    <div class="text-right ml-3 shrink-0 space-y-1">
                        <div class="font-bold text-sm">₦{{ number_format($invoice->total_amount, 2) }}</div>
                        <a href="{{ route('receipt.show', $invoice->id) }}" target="_blank"
                            class="btn btn-xs btn-outline btn-primary">
                            <x-icon name="o-printer" class="w-3 h-3" /> Receipt
                        </a>
                    </div>
                </div>
            @empty
                <div class="text-center py-6 text-base-content/60 text-sm">No recent payments.</div>
            @endforelse
        </x-card>
    </div>

    <!-- Online Orders — Cashier Actions -->
    @php $totalOnline = $prePaidOrders->count() + $codDeliveryOrders->count() + $codPickupOrders->count(); @endphp
    @if($totalOnline > 0)
    <div class="mt-4 space-y-3">

        {{-- 1. Pre-paid orders: verify payment --}}
        @if($prePaidOrders->count() > 0)
        <x-card title="Pre-paid Orders" subtitle="{{ $prePaidOrders->count() }} ready — verify payment before dispatch">
            @foreach($prePaidOrders as $order)
                <div class="flex justify-between items-center p-3 bg-info/10 border border-info/30 rounded-lg mb-2">
                    <div class="min-w-0 flex-1">
                        <div class="font-bold text-sm">{{ $order->order_number }}</div>
                        <div class="text-xs text-base-content/60 truncate">
                            {{ $order->customer?->name ?? $order->guest_name ?? 'Guest' }}
                            · {{ ucfirst($order->fulfillment_type) }}
                        </div>
                        <div class="text-xs text-base-content/60">Packed by: {{ $order->claimedByUser?->name ?? '—' }}</div>
                        @if($order->payment_reference)
                            <div class="text-xs text-info font-medium">Ref: {{ $order->payment_reference }}</div>
                        @endif
                    </div>
                    <div class="text-right ml-3 shrink-0 space-y-1">
                        <div class="font-bold text-info">₦{{ number_format($order->total_amount, 2) }}</div>
                        <a href="{{ route('order.invoice', $order->id) }}" target="_blank"
                           class="btn btn-xs btn-outline btn-info">
                            <x-icon name="o-printer" class="w-3 h-3" /> Invoice
                        </a>
                        <x-button
                            label="Verify Paid"
                            wire:click="verifyOrderPayment({{ $order->id }})"
                            wire:confirm="Confirm that payment of ₦{{ number_format($order->total_amount, 2) }} has been received for order {{ $order->order_number }}?"
                            class="btn-xs btn-success"
                            icon="o-check-badge"
                        />
                    </div>
                </div>
            @endforeach
        </x-card>
        @endif

        {{-- 2. COD delivery orders: approve dispatch --}}
        @if($codDeliveryOrders->count() > 0)
        <x-card title="COD — Delivery" subtitle="{{ $codDeliveryOrders->count() }} ready — approve for dispatch (rider collects on delivery)">
            @foreach($codDeliveryOrders as $order)
                <div class="flex justify-between items-center p-3 bg-warning/10 border border-warning/30 rounded-lg mb-2">
                    <div class="min-w-0 flex-1">
                        <div class="font-bold text-sm">{{ $order->order_number }}</div>
                        <div class="text-xs text-base-content/60 truncate">
                            {{ $order->customer?->name ?? $order->guest_name ?? 'Guest' }}
                            @if($order->delivery_address)· <span class="italic">{{ Str::limit($order->delivery_address, 30) }}</span>@endif
                        </div>
                        <div class="text-xs text-base-content/60">Packed by: {{ $order->claimedByUser?->name ?? '—' }}</div>
                    </div>
                    <div class="text-right ml-3 shrink-0 space-y-1">
                        <div class="font-bold text-warning">₦{{ number_format($order->total_amount, 2) }}</div>
                        <a href="{{ route('order.invoice', $order->id) }}" target="_blank"
                           class="btn btn-xs btn-outline btn-warning">
                            <x-icon name="o-printer" class="w-3 h-3" /> Invoice
                        </a>
                        <x-button
                            label="Approve Dispatch"
                            wire:click="approveCodDispatch({{ $order->id }})"
                            wire:confirm="Approve order {{ $order->order_number }} for COD delivery? Rider will collect ₦{{ number_format($order->total_amount, 2) }} on delivery."
                            class="btn-xs btn-warning"
                            icon="o-truck"
                        />
                    </div>
                </div>
            @endforeach
        </x-card>
        @endif

        {{-- 3. COD pickup orders: collect payment at counter --}}
        @if($codPickupOrders->count() > 0)
        <x-card title="COD — Pickup" subtitle="{{ $codPickupOrders->count() }} ready — collect payment before handing over">
            @foreach($codPickupOrders as $order)
                <div class="flex justify-between items-center p-3 bg-error/10 border border-error/30 rounded-lg mb-2">
                    <div class="min-w-0 flex-1">
                        <div class="font-bold text-sm">{{ $order->order_number }}</div>
                        <div class="text-xs text-base-content/60 truncate">
                            {{ $order->customer?->name ?? $order->guest_name ?? 'Guest' }}
                            · Pickup
                        </div>
                        <div class="text-xs text-base-content/60">Packed by: {{ $order->claimedByUser?->name ?? '—' }}</div>
                    </div>
                    <div class="text-right ml-3 shrink-0 space-y-1">
                        <div class="font-bold text-error">₦{{ number_format($order->total_amount, 2) }}</div>
                        <a href="{{ route('order.invoice', $order->id) }}" target="_blank"
                           class="btn btn-xs btn-outline btn-error">
                            <x-icon name="o-printer" class="w-3 h-3" /> Invoice
                        </a>
                        <x-button
                            label="Collect Cash"
                            wire:click="openOrderPayment({{ $order->id }})"
                            class="btn-xs btn-error"
                            icon="o-banknotes"
                        />
                    </div>
                </div>
            @endforeach
        </x-card>
        @endif

    </div>
    @endif

    <!-- Payment Modal -->
    <x-modal wire:model="payModal" title="{{ $paySuccess ? 'Payment Confirmed' : ($payReview ? 'Review & Confirm' : 'Process Payment') }}" box-class="max-w-lg relative overflow-hidden">
        @if($paySuccess && $payingSale)
            <div class="text-center py-6">
                <x-icon name="o-check-circle" class="w-16 h-16 text-success mx-auto mb-3" />
                <div class="text-xl font-bold mb-1">Payment Received!</div>
                <div class="text-base-content/60 text-sm mb-1">{{ $payingSale->invoice_number }}</div>
                <div class="text-2xl font-bold text-primary mb-5">₦{{ number_format($payingSale->total_amount, 2) }}</div>

                <div class="bg-base-200 rounded-lg p-3 mb-4 text-sm text-left space-y-1">
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Customer</span>
                        <span>{{ $payingSale->customer?->name ?? 'Walk-in' }}</span>
                    </div>
                    @if($payingSale->payment_details)
                        @php
                            $pd = $payingSale->payment_details;
                            $pmtMethods = array_filter([
                                'cash'     => $pd['cash'] ?? null,
                                'card'     => $pd['card'] ?? null,
                                'transfer' => $pd['transfer'] ?? null,
                                'credit'   => $pd['credit'] ?? null,
                            ]);
                        @endphp
                        @foreach($pmtMethods as $method => $amount)
                            <div class="flex justify-between">
                                <span class="text-base-content/60">{{ ucfirst($method) }}</span>
                                <span>₦{{ number_format($amount, 2) }}</span>
                            </div>
                        @endforeach
                        @if(!empty($pd['debts_cleared']))
                            <div class="border-t border-base-300 pt-1.5 mt-0.5 space-y-0.5">
                                <div class="text-xs font-semibold text-base-content/50 uppercase tracking-wide">Debts settled</div>
                                @foreach($pd['debts_cleared'] as $cleared)
                                    <div class="flex justify-between text-xs">
                                        <span class="text-base-content/70">{{ $cleared['invoice'] }} ✓</span>
                                        <span class="text-success font-medium">₦{{ number_format($cleared['amount'], 2) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        @if(!empty($pd['shortfall']))
                            <div class="flex justify-between border-t border-base-300 pt-1.5 mt-0.5 text-error">
                                <span class="font-semibold">New debt recorded</span>
                                <span class="font-bold">₦{{ number_format($pd['shortfall'], 2) }}</span>
                            </div>
                        @endif
                        @if(!empty($pd['change_given']))
                            <div class="flex justify-between text-success border-t border-base-300 pt-1.5 mt-0.5">
                                <span>Change given</span>
                                <span class="font-medium">₦{{ number_format($pd['change_given'], 2) }}</span>
                            </div>
                        @endif
                        @if(!empty($pd['stored_credit']))
                            <div class="flex justify-between text-info border-t border-base-300 pt-1.5 mt-0.5">
                                <span>Stored as credit</span>
                                <span class="font-medium">₦{{ number_format($pd['stored_credit'], 2) }}</span>
                            </div>
                        @endif
                    @else
                        <div class="flex justify-between">
                            <span class="text-base-content/60">Payment</span>
                            <span>{{ ucfirst($payingSale->payment_method) }}</span>
                        </div>
                    @endif
                </div>

                @if($customerDebt && $customerDebt->debt_count > 0)
                    <div class="alert alert-error py-2 mb-3 text-xs">
                        <x-icon name="o-exclamation-triangle" class="w-4 h-4 shrink-0" />
                        <span>
                            <span class="font-bold">{{ $payingSale->customer?->name }}</span>
                            still owes <span class="font-bold">₦{{ number_format($customerDebt->total_balance, 2) }}</span>
                            across {{ $customerDebt->debt_count }} {{ Str::plural('invoice', $customerDebt->debt_count) }}.
                        </span>
                    </div>
                @elseif($payingSale->customer_id && ($payingSale->customer->credit_balance ?? 0) > 0)
                    <div class="text-xs text-info font-semibold mb-3">
                        {{ $payingSale->customer->name }}'s credit balance: ₦{{ number_format($payingSale->customer->credit_balance, 2) }}
                    </div>
                @endif
                <p class="text-sm text-base-content/60 mb-4">Print 2 copies — customer returns one to the sales person as proof of payment.</p>

                <div class="flex gap-2 justify-center">
                    <a href="{{ route('receipt.show', $lastPaidSaleId) }}" target="_blank"
                        class="btn btn-primary gap-2">
                        <x-icon name="o-printer" class="w-4 h-4" />
                        Print Receipt (2 copies)
                    </a>
                    <x-button label="Done" wire:click="closePay" class="btn-ghost" />
                </div>
            </div>

        @elseif($payingSale)
            {{-- Invoice summary --}}
            <div class="bg-base-200 rounded-lg p-3 mb-4">
                <div class="flex justify-between mb-1">
                    <span class="font-bold">{{ $payingSale->invoice_number }}</span>
                    <span class="font-bold text-primary">₦{{ number_format($payingSale->total_amount, 2) }}</span>
                </div>
                <div class="text-xs text-base-content/60">
                    {{ $payingSale->customer?->name ?? 'Walk-in' }} | {{ $payingSale->user->name }}
                </div>
                <x-hr class="my-2" />
                <div class="space-y-1 max-h-32 overflow-y-auto">
                    @foreach($payingSale->saleItems as $item)
                        <div class="flex justify-between text-xs">
                            <span class="truncate flex-1">{{ $item->product->name }} × {{ $item->quantity }}</span>
                            <span class="ml-2 shrink-0">₦{{ number_format($item->subtotal, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Customer credit & debt notices --}}
            @if($payingSale->customer_id)
                @php $creditBal = (float)($payingSale->customer->credit_balance ?? 0); @endphp
                @if($creditBal > 0)
                    <div class="alert alert-success mb-3 py-2">
                        <x-icon name="o-gift" class="w-4 h-4 shrink-0" />
                        <div class="flex-1 text-xs">
                            <span class="font-bold">{{ $payingSale->customer->name }}</span> has
                            <span class="font-bold">₦{{ number_format($creditBal, 2) }}</span> store credit.
                        </div>
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" wire:model.live="apply_credit" class="checkbox checkbox-success checkbox-xs" />
                            <span class="text-xs font-semibold">Apply</span>
                        </label>
                    </div>
                @endif

                @if($customerDebt)
                    <div class="alert alert-warning mb-3 py-2">
                        <x-icon name="o-exclamation-triangle" class="w-4 h-4 shrink-0" />
                        <div class="text-xs">
                            <span class="font-bold">{{ $payingSale->customer->name }}</span> owes
                            <span class="font-bold">₦{{ number_format($customerDebt->total_balance, 2) }}</span>
                            across {{ $customerDebt->debt_count }} {{ Str::plural('invoice', $customerDebt->debt_count) }}.
                            Extra payment will auto-clear oldest first.
                        </div>
                    </div>
                @endif
            @endif

            <x-form wire:submit="processPayment">
                {{-- Three payment inputs --}}
                <div class="grid grid-cols-3 gap-2">
                    <x-input wire:model.live="cash_tendered" prefix="₦" type="number" step="0.01" min="0" label="Cash" placeholder="0" />
                    <x-input wire:model.live="card_amount" prefix="₦" type="number" step="0.01" min="0" label="Card" placeholder="0" />
                    <x-input wire:model.live="transfer_amount" prefix="₦" type="number" step="0.01" min="0" label="Transfer" placeholder="0" />
                </div>

                {{-- Live breakdown --}}
                @if($breakdown)
                    <div class="bg-base-200 rounded-lg p-3 mt-3 text-sm space-y-1.5">
                        @if($breakdown['credit_used'] > 0.01)
                            <div class="flex justify-between text-success">
                                <span>Store credit applied</span>
                                <span class="font-medium">−₦{{ number_format($breakdown['credit_used'], 2) }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between">
                            <span class="text-base-content/60">Total covered</span>
                            <span class="font-bold">₦{{ number_format($breakdown['total_collected'], 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-base-content/60">Invoice total</span>
                            <span>₦{{ number_format($breakdown['sale_total'], 2) }}</span>
                        </div>

                        @if($breakdown['shortfall'] > 0.01)
                            <div class="flex justify-between border-t border-base-300 pt-1.5 mt-1">
                                <span class="font-semibold text-error">Shortfall</span>
                                <span class="font-bold text-error">₦{{ number_format($breakdown['shortfall'], 2) }}</span>
                            </div>
                            @if($payingSale->customer_id)
                                <p class="text-xs text-warning">Will be recorded as a debt for {{ $payingSale->customer->name }}.</p>
                            @else
                                <p class="text-xs text-error font-semibold">Walk-in must pay the full amount.</p>
                            @endif
                        @endif

                        @if(!empty($breakdown['debt_allocations']))
                            <div class="border-t border-base-300 pt-1.5 mt-1 space-y-1.5">
                                <div class="text-xs font-semibold text-base-content/60 uppercase">Debt payments</div>
                                @foreach($breakdown['debt_allocations'] as $alloc)
                                    <div class="bg-base-300/50 rounded p-1.5 text-xs space-y-0.5">
                                        <div class="flex justify-between font-semibold">
                                            <span>{{ $alloc['invoice'] }}</span>
                                            <span class="text-base-content/60">Owed: ₦{{ number_format($alloc['owed'], 2) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-success">Paying: ₦{{ number_format($alloc['paying'], 2) }}</span>
                                            @if($alloc['remaining'] > 0.01)
                                                <span class="text-error font-semibold">Still owes: ₦{{ number_format($alloc['remaining'], 2) }}</span>
                                            @else
                                                <span class="text-success font-semibold">Fully cleared</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if($breakdown['change_back'] > 0.01 || $breakdown['stored_as_credit'] > 0.01)
                            @php $changeAmount = $breakdown['stored_as_credit'] > 0.01 ? $breakdown['stored_as_credit'] : $breakdown['change_back']; @endphp
                            <div class="border-t border-base-300 pt-1.5 mt-1">
                                @if($breakdown['stored_as_credit'] > 0.01)
                                    <div class="flex justify-between text-info font-semibold">
                                        <span>Stored as credit</span>
                                        <span>₦{{ number_format($breakdown['stored_as_credit'], 2) }}</span>
                                    </div>
                                @else
                                    <div class="flex justify-between text-success font-semibold">
                                        <span>Change back</span>
                                        <span class="text-base">₦{{ number_format($breakdown['change_back'], 2) }}</span>
                                    </div>
                                @endif
                                {{-- Store as credit option --}}
                                @if($payingSale->customer_id)
                                    <label class="flex items-center gap-2 mt-2 cursor-pointer">
                                        <input type="checkbox" wire:model.live="store_change_as_credit" class="checkbox checkbox-info checkbox-sm" />
                                        <span class="text-xs {{ $breakdown['stored_as_credit'] > 0.01 ? 'text-info' : 'text-base-content/60' }} font-medium">
                                            Store ₦{{ number_format($changeAmount, 2) }} as credit — cashier has no change
                                        </span>
                                    </label>
                                @endif
                            </div>
                        @endif

                        {{-- @if($breakdown['shortfall'] < 0.01 && $breakdown['excess'] >= -0.01)
                            <div class="text-center text-xs text-success font-semibold border-t border-base-300 pt-1.5 mt-1">
                                ✓ Invoice fully covered
                            </div>
                        @endif --}}
                    </div>
                @endif

                {{-- Walk-in WhatsApp --}}
                @if(!$payingSale->customer_id)
                    <x-input
                        wire:model="walkin_phone"
                        label="WhatsApp for receipt (optional)"
                        placeholder="08012345678"
                        icon="o-chat-bubble-left-ellipsis"
                        hint="Leave blank to skip"
                        class="mt-2"
                    />
                @endif

                <x-slot:actions>
                    <x-button label="Cancel" @click="$wire.payModal = false" />
                    <x-button
                        label="Review Payment"
                        type="button"
                        wire:click="$set('payReview', true)"
                        class="btn-primary"
                        icon="o-arrow-right"
                        :disabled="!$breakdown || !$breakdown['can_confirm']"
                    />
                </x-slot:actions>
            </x-form>

            @if($payReview && $breakdown)
                {{-- Summary overlay --}}
                <div class="absolute inset-0 bg-base-100 z-10 p-5 flex flex-col rounded-box">
                    <div class="text-base font-bold mb-3">Confirm Payment</div>

                    <div class="bg-base-200 rounded-lg p-3 space-y-1.5 text-sm flex-1 overflow-y-auto">
                        <div class="flex justify-between">
                            <span class="text-base-content/60">Invoice</span>
                            <span class="font-bold">{{ $payingSale->invoice_number }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-base-content/60">Customer</span>
                            <span>{{ $payingSale->customer?->name ?? 'Walk-in' }}</span>
                        </div>

                        <div class="border-t border-base-300 pt-2 mt-2 space-y-1">
                            @if((float)($cash_tendered) > 0)
                                <div class="flex justify-between"><span class="text-base-content/60">Cash</span><span>₦{{ number_format((float)$cash_tendered, 2) }}</span></div>
                            @endif
                            @if((float)($card_amount) > 0)
                                <div class="flex justify-between"><span class="text-base-content/60">Card</span><span>₦{{ number_format((float)$card_amount, 2) }}</span></div>
                            @endif
                            @if((float)($transfer_amount) > 0)
                                <div class="flex justify-between"><span class="text-base-content/60">Transfer</span><span>₦{{ number_format((float)$transfer_amount, 2) }}</span></div>
                            @endif
                            @if($breakdown['credit_used'] > 0.01)
                                <div class="flex justify-between text-success"><span>Store credit</span><span>₦{{ number_format($breakdown['credit_used'], 2) }}</span></div>
                            @endif
                        </div>

                        <div class="border-t border-base-300 pt-2 mt-2 space-y-1">
                            <div class="flex justify-between font-bold text-base">
                                <span>Invoice total</span>
                                <span>₦{{ number_format($breakdown['sale_total'], 2) }}</span>
                            </div>
                            @if($breakdown['shortfall'] > 0.01)
                                <div class="flex justify-between text-error font-semibold">
                                    <span>Debt recorded</span>
                                    <span>₦{{ number_format($breakdown['shortfall'], 2) }}</span>
                                </div>
                            @endif
                            @if(!empty($breakdown['debt_allocations']))
                                @foreach($breakdown['debt_allocations'] as $alloc)
                                    <div class="flex justify-between text-success text-xs">
                                        <span>Clears {{ $alloc['invoice'] }}</span>
                                        <span>₦{{ number_format($alloc['paying'], 2) }}</span>
                                    </div>
                                @endforeach
                            @endif
                            @if($breakdown['stored_as_credit'] > 0.01)
                                <div class="flex justify-between text-info font-semibold">
                                    <span>Stored as credit</span>
                                    <span>₦{{ number_format($breakdown['stored_as_credit'], 2) }}</span>
                                </div>
                            @elseif($breakdown['change_back'] > 0.01)
                                <div class="flex justify-between text-success font-semibold">
                                    <span>Change to give</span>
                                    <span>₦{{ number_format($breakdown['change_back'], 2) }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="alert alert-warning py-2 mt-3 text-xs">
                        <x-icon name="o-exclamation-triangle" class="w-4 h-4 shrink-0" />
                        Confirm payment information.
                    </div>

                    <div class="flex gap-2 mt-3">
                        <x-button label="Back" wire:click="$set('payReview', false)" class="btn-ghost flex-1" icon="o-arrow-left" />
                        <x-button label="Confirm & Pay" wire:click="processPayment" class="btn-success flex-1" icon="o-check" />
                    </div>
                </div>
            @endif
        @endif
    </x-modal>

    <!-- Online Order Payment Modal -->
    <x-modal wire:model="orderPayModal" title="{{ $orderPaySuccess ? 'Payment Collected' : ($orderPayReview ? 'Review & Confirm' : 'Collect Order Payment') }}" box-class="max-w-lg relative overflow-hidden">
        @if($orderPaySuccess && $payingOrder)
            <div class="text-center py-6">
                <x-icon name="o-check-circle" class="w-16 h-16 text-success mx-auto mb-3" />
                <div class="text-xl font-bold mb-1">Payment Collected!</div>
                <div class="text-base-content/60 text-sm mb-1">{{ $payingOrder->order_number }}</div>
                <div class="text-2xl font-bold text-primary mb-4">₦{{ number_format($payingOrder->total_amount, 2) }}</div>
                <p class="text-sm text-base-content/60 mb-4">Order is now completed. Print receipt and hand goods to customer.</p>
                <div class="flex gap-2 justify-center">
                    @if($lastPaidOrderId)
                        <a href="{{ route('order.receipt', $lastPaidOrderId) }}" target="_blank"
                           class="btn btn-primary gap-2">
                            <x-icon name="o-printer" class="w-4 h-4" /> Print Receipt
                        </a>
                    @endif
                    <x-button label="Done" wire:click="closeOrderPay" class="btn-ghost" />
                </div>
            </div>

        @elseif($payingOrder)
            {{-- Order summary --}}
            <div class="bg-base-200 rounded-lg p-3 mb-4">
                <div class="flex justify-between mb-1">
                    <span class="font-bold">{{ $payingOrder->order_number }}</span>
                    <span class="font-bold text-warning">₦{{ number_format($payingOrder->total_amount, 2) }}</span>
                </div>
                <div class="text-xs text-base-content/60 mb-2">
                    {{ $payingOrder->customer?->name ?? $payingOrder->guest_name ?? 'Guest' }}
                    · {{ ucfirst($payingOrder->fulfillment_type) }}
                    · Packed by {{ $payingOrder->claimedByUser?->name ?? '—' }}
                </div>
                <x-hr class="my-2" />
                <div class="space-y-1 max-h-32 overflow-y-auto">
                    @foreach($payingOrder->items as $item)
                        <div class="flex justify-between text-xs">
                            <span class="truncate flex-1">{{ $item->product->name }} × {{ $item->quantity }}</span>
                            <span class="ml-2 shrink-0">₦{{ number_format($item->subtotal, 2) }}</span>
                        </div>
                    @endforeach
                </div>
                @if($payingOrder->delivery_fee > 0)
                    <div class="flex justify-between text-xs mt-1 pt-1 border-t border-base-300">
                        <span class="text-base-content/60">Delivery fee</span>
                        <span>₦{{ number_format($payingOrder->delivery_fee, 2) }}</span>
                    </div>
                @endif
            </div>

            <x-form wire:submit="processOrderPayment">
                <div class="grid grid-cols-3 gap-2">
                    <x-input wire:model.live="cash_tendered" prefix="₦" type="number" step="0.01" min="0" label="Cash" placeholder="0" />
                    <x-input wire:model.live="card_amount" prefix="₦" type="number" step="0.01" min="0" label="Card" placeholder="0" />
                    <x-input wire:model.live="transfer_amount" prefix="₦" type="number" step="0.01" min="0" label="Transfer" placeholder="0" />
                </div>

                @if($orderBreakdown)
                    <div class="bg-base-200 rounded-lg p-3 mt-3 text-sm space-y-1.5">
                        <div class="flex justify-between">
                            <span class="text-base-content/60">Collected</span>
                            <span class="font-bold">₦{{ number_format($orderBreakdown['total_collected'], 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-base-content/60">Order total</span>
                            <span>₦{{ number_format($orderBreakdown['order_total'], 2) }}</span>
                        </div>
                        @if($orderBreakdown['shortfall'] > 0.01)
                            <div class="flex justify-between border-t border-base-300 pt-1.5 mt-1 text-error font-semibold">
                                <span>Shortfall</span>
                                <span>₦{{ number_format($orderBreakdown['shortfall'], 2) }}</span>
                            </div>
                            <p class="text-xs text-error">Online orders must be paid in full.</p>
                        @elseif($orderBreakdown['change'] > 0.01)
                            <div class="flex justify-between border-t border-base-300 pt-1.5 mt-1 text-success font-semibold">
                                <span>Change back</span>
                                <span>₦{{ number_format($orderBreakdown['change'], 2) }}</span>
                            </div>
                        @else
                            <div class="text-center text-xs text-success font-semibold border-t border-base-300 pt-1.5 mt-1">
                                ✓ Fully covered
                            </div>
                        @endif
                    </div>
                @endif

                <x-slot:actions>
                    <x-button label="Cancel" @click="$wire.orderPayModal = false" />
                    <x-button
                        label="Review Payment"
                        type="button"
                        wire:click="$set('orderPayReview', true)"
                        class="btn-warning"
                        icon="o-arrow-right"
                        :disabled="!$orderBreakdown || !$orderBreakdown['can_confirm']"
                    />
                </x-slot:actions>
            </x-form>

            @if($orderPayReview && $orderBreakdown)
                <div class="absolute inset-0 bg-base-100 z-10 p-5 flex flex-col rounded-box">
                    <div class="text-base font-bold mb-3">Confirm Order Payment</div>

                    <div class="bg-base-200 rounded-lg p-3 space-y-1.5 text-sm flex-1 overflow-y-auto">
                        <div class="flex justify-between">
                            <span class="text-base-content/60">Order</span>
                            <span class="font-bold">{{ $payingOrder->order_number }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-base-content/60">Customer</span>
                            <span>{{ $payingOrder->customer?->name ?? $payingOrder->guest_name ?? 'Guest' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-base-content/60">Type</span>
                            <span>{{ ucfirst($payingOrder->fulfillment_type) }} — Pickup</span>
                        </div>

                        <div class="border-t border-base-300 pt-2 mt-2 space-y-1">
                            @if((float)($cash_tendered) > 0)
                                <div class="flex justify-between"><span class="text-base-content/60">Cash</span><span>₦{{ number_format((float)$cash_tendered, 2) }}</span></div>
                            @endif
                            @if((float)($card_amount) > 0)
                                <div class="flex justify-between"><span class="text-base-content/60">Card</span><span>₦{{ number_format((float)$card_amount, 2) }}</span></div>
                            @endif
                            @if((float)($transfer_amount) > 0)
                                <div class="flex justify-between"><span class="text-base-content/60">Transfer</span><span>₦{{ number_format((float)$transfer_amount, 2) }}</span></div>
                            @endif
                        </div>

                        <div class="border-t border-base-300 pt-2 mt-2 space-y-1">
                            <div class="flex justify-between font-bold text-base">
                                <span>Order total</span>
                                <span>₦{{ number_format($orderBreakdown['order_total'], 2) }}</span>
                            </div>
                            @if($orderBreakdown['change'] > 0.01)
                                <div class="flex justify-between text-success font-semibold">
                                    <span>Change to give</span>
                                    <span>₦{{ number_format($orderBreakdown['change'], 2) }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="alert alert-warning py-2 mt-3 text-xs">
                        <x-icon name="o-exclamation-triangle" class="w-4 h-4 shrink-0" />
                        Confirm only once the goods have been handed over by the sales person.
                    </div>

                    <div class="flex gap-2 mt-3">
                        <x-button label="Back" wire:click="$set('orderPayReview', false)" class="btn-ghost flex-1" icon="o-arrow-left" />
                        <x-button label="Confirm & Pay" wire:click="processOrderPayment" class="btn-success flex-1" icon="o-check" />
                    </div>
                </div>
            @endif
        @endif
    </x-modal>

    @script
    <script>
        $wire.on('new-invoice', () => {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.frequency.value = 800;
                gain.gain.value = 0.3;
                osc.start();
                osc.stop(ctx.currentTime + 0.2);
                setTimeout(() => {
                    const osc2 = ctx.createOscillator();
                    const gain2 = ctx.createGain();
                    osc2.connect(gain2);
                    gain2.connect(ctx.destination);
                    osc2.frequency.value = 1000;
                    gain2.gain.value = 0.3;
                    osc2.start();
                    osc2.stop(ctx.currentTime + 0.3);
                }, 250);
            } catch(e) {}
        });
    </script>
    @endscript
</div>
