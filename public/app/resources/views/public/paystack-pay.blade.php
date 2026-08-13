<x-layouts.public title="Payment">
    <div class="max-w-md mx-auto px-4 py-12 text-center">
        <h1 class="text-xl font-bold mb-2">Complete Payment</h1>
        <p class="text-base-content/60 mb-4">Order: <strong>{{ $order->order_number }}</strong></p>
        <p class="text-2xl font-bold text-primary mb-6">₦{{ number_format($order->total_amount, 2) }}</p>

        <button id="paystack-btn" class="btn btn-primary btn-block btn-lg">
            <x-icon name="o-lock-closed" class="w-5 h-5" /> Pay with Paystack
        </button>

        <a href="/account" class="btn btn-ghost btn-sm mt-4">Cancel</a>
    </div>

    <script src="https://js.paystack.co/v1/inline.js"></script>
    <script>
        document.getElementById('paystack-btn').addEventListener('click', function() {
            var handler = PaystackPop.setup({
                key: '{{ $paystackKey }}',
                email: '{{ $email }}',
                amount: {{ $amount }},
                ref: '{{ $reference }}',
                metadata: {
                    order_id: {{ $order->id }},
                },
                callback: function(response) {
                    window.location.href = '/paystack/callback?reference=' + response.reference;
                },
                onClose: function() {
                    // user closed popup
                }
            });
            handler.openIframe();
        });
    </script>
</x-layouts.public>
