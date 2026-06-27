<x-layouts.public title="Order Confirmed">
    <div class="max-w-md mx-auto px-4 py-12 text-center">
        <x-icon name="o-check-circle" class="w-16 h-16 text-success mx-auto mb-4" />
        <h1 class="text-2xl font-bold mb-2">Order Placed!</h1>
        <p class="text-base-content/60 mb-6">Your order <strong>{{ $order->order_number }}</strong> has been received.</p>

        <div class="card bg-base-100 border border-base-200 p-4 text-left mb-6">
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-base-content/60">Order #:</span><span class="font-semibold">{{ $order->order_number }}</span></div>
                <div class="flex justify-between"><span class="text-base-content/60">Total:</span><span class="font-bold text-primary">₦{{ number_format($order->total_amount, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-base-content/60">Payment:</span><span>{{ $order->payment_method === 'paystack' ? 'Online' : 'Pay on ' . ucfirst($order->fulfillment_type) }}</span></div>
                <div class="flex justify-between"><span class="text-base-content/60">Fulfillment:</span><span>{{ ucfirst($order->fulfillment_type) }}</span></div>
                <div class="flex justify-between"><span class="text-base-content/60">Status:</span><span class="badge badge-warning badge-sm">{{ ucfirst($order->status) }}</span></div>
            </div>
        </div>

        <div class="flex flex-col gap-2">
            <a href="/account" class="btn btn-primary">View My Orders</a>
            <a href="/shop" class="btn btn-ghost">Continue Shopping</a>
        </div>
    </div>
</x-layouts.public>
