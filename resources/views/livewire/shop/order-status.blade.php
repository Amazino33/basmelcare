{{-- Polled, because the customer is watching this page while the pharmacy
     works on the order. wire:poll is the pattern used everywhere else here;
     nothing in this app broadcasts. --}}
<div class="max-w-md mx-auto px-4 py-8" wire:poll.30s>

    @if($justPlaced)
        <div class="text-center mb-6">
            <x-icon name="o-check-circle" class="w-14 h-14 text-success mx-auto mb-3" />
            <h1 class="text-2xl font-bold">Order placed</h1>
            <p class="text-base-content/60 text-sm mt-1">
                We have your order. This page shows where it has got to — keep the link,
                or <a href="{{ route('order.find') }}" class="link">find it again</a>
                with your order number and phone.
            </p>
        </div>
    @else
        <div class="text-center mb-6">
            <h1 class="text-xl font-bold">{{ $order->progressLabel() }}</h1>
            <p class="text-base-content/60 text-sm mt-1">Order {{ $order->order_number }}</p>
        </div>
    @endif

    {{-- Where it has got to. A cancelled order has no trail to walk, so it
         is said plainly instead. --}}
    @if($order->status === 'cancelled')
        <div class="alert alert-error mb-4 text-sm">
            <x-icon name="o-x-circle" class="w-5 h-5 shrink-0" />
            <span>This order was cancelled. Nothing has been charged for it.</span>
        </div>
    @else
        <div class="card bg-base-100 border border-base-200 p-4 mb-4">
            <ol class="space-y-3">
                @foreach($order->progressSteps() as $step)
                    <li class="flex items-start gap-3">
                        <span @class([
                            'w-6 h-6 rounded-full flex items-center justify-center shrink-0 mt-0.5',
                            'bg-success text-success-content' => $step['done'],
                            'bg-primary text-primary-content' => $step['current'],
                            'bg-base-200 text-base-content/40' => ! $step['done'] && ! $step['current'],
                        ])>
                            @if($step['done'])
                                <x-icon name="o-check" class="w-3.5 h-3.5" />
                            @else
                                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                            @endif
                        </span>

                        <div class="min-w-0">
                            <p @class([
                                'text-sm',
                                'font-semibold' => $step['current'],
                                'text-base-content/50' => ! $step['done'] && ! $step['current'],
                            ])>{{ $step['label'] }}</p>

                            @if($step['current'] && $step['hint'])
                                <p class="text-xs text-base-content/60 mt-0.5">{{ $step['hint'] }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    {{-- Who is bringing it. A customer with the rider's number does not have
         to ring the counter to ask where their medicine is. --}}
    @if($order->isOnItsWay() && $order->delivery_person_name)
        <div class="card bg-info/10 border border-info/20 p-4 mb-4">
            <div class="flex items-start gap-3">
                <x-icon name="o-truck" class="w-5 h-5 shrink-0 text-info mt-0.5" />
                <div class="text-sm min-w-0">
                    <p><span class="font-semibold">{{ $order->delivery_person_name }}</span> is bringing this to you.</p>
                    @if($order->delivery_person_phone)
                        <a href="tel:{{ $order->delivery_person_phone }}" class="btn btn-sm btn-info mt-2">
                            <x-icon name="o-phone" class="w-4 h-4" /> Call {{ $order->delivery_person_phone }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if($order->fulfillment_type === 'pickup' && $order->status === 'ready')
        <div class="card bg-primary/5 border border-primary/20 p-4 mb-4 text-sm">
            <p class="font-semibold">Ready to collect</p>
            <p class="text-base-content/70 text-xs mt-1">
                {{ \App\Models\AppSetting::get('pharmacy_address', 'Our pharmacy') }}
            </p>
        </div>
    @endif

    {{-- What was ordered and what it came to. --}}
    <div class="card bg-base-100 border border-base-200 p-4 mb-4">
        <div class="flex justify-between items-start mb-3">
            <div class="min-w-0">
                <p class="font-semibold text-sm">{{ $order->order_number }}</p>
                <p class="text-xs text-base-content/60">{{ $order->created_at->format('j M Y, g:i a') }}</p>
            </div>
            <span @class([
                'badge badge-sm shrink-0',
                'badge-warning' => $order->payment_status === 'pending',
                'badge-success' => $order->payment_status === 'paid',
            ])>{{ $order->payment_status === 'paid' ? 'Paid' : 'Not yet paid' }}</span>
        </div>

        <div class="space-y-1 border-t border-base-200 pt-3">
            @foreach($order->items as $item)
                <div class="flex justify-between text-xs gap-2">
                    <span class="text-base-content/60 min-w-0">{{ $item->product?->name ?? 'Item' }} × {{ $item->quantity }}</span>
                    <span class="shrink-0">₦{{ number_format($item->subtotal, 2) }}</span>
                </div>
            @endforeach
        </div>

        <div class="space-y-1 border-t border-base-200 mt-3 pt-3 text-sm">
            <div class="flex justify-between">
                <span class="text-base-content/60">Subtotal</span>
                <span>₦{{ number_format($order->subtotal, 2) }}</span>
            </div>

            @if($order->insurance_covered > 0)
                <div class="flex justify-between text-success">
                    <span>Your cover paid</span>
                    <span>−₦{{ number_format($order->insurance_covered, 2) }}</span>
                </div>
            @endif

            @if($order->fulfillment_type === 'delivery')
                <div class="flex justify-between">
                    <span class="text-base-content/60">
                        Delivery
                        @if($order->delivery_area)
                            <span class="block text-xs">{{ $order->delivery_area }}</span>
                        @endif
                    </span>
                    <span>{{ $order->delivery_fee > 0 ? '₦' . number_format($order->delivery_fee, 2) : 'Free' }}</span>
                </div>
            @endif

            <div class="flex justify-between font-bold border-t border-base-200 pt-2">
                <span>Total</span>
                <span class="text-primary">₦{{ number_format($order->total_amount, 2) }}</span>
            </div>
        </div>
    </div>

    @if($order->payment_status === 'pending' && $order->payment_method === 'paystack' && $order->status !== 'cancelled')
        <a href="{{ route('order.pay', $order->public_token) }}" class="btn btn-primary btn-block mb-2">
            Pay now
        </a>
    @endif

    <div class="flex flex-col gap-2">
        @auth('customer')
            <a href="/account" class="btn btn-ghost btn-block">My orders</a>
        @endauth
        <a href="/shop" class="btn btn-ghost btn-block">Continue shopping</a>
    </div>
</div>
