<x-layouts.public title="Consultation payment">
    <div class="max-w-md mx-auto px-4 py-12 text-center">
        <h1 class="text-xl font-bold mb-2">Confirm your consultation</h1>

        <p class="text-base-content/60 text-sm">
            {{ $appointment->modeLabel() }} with a {{ strtolower($appointment->providerLabel()) }}
        </p>
        <p class="text-base-content/60 text-sm mb-4">
            {{ $appointment->scheduled_at->format('D, d M Y') }} at {{ $appointment->scheduled_at->format('g:i A') }}
        </p>

        <p class="text-2xl font-bold text-primary mb-6">&#8358;{{ number_format($appointment->price, 2) }}</p>

        <button id="paystack-btn" class="btn btn-primary btn-block btn-lg">
            <x-icon name="o-lock-closed" class="w-5 h-5" /> Pay with Paystack
        </button>

        <p class="text-xs text-base-content/50 mt-4">
            Paying holds your request. We will call you to agree the time, or suggest
            another if that one is taken.
        </p>

        <a href="{{ route('consultation.book') }}" class="btn btn-ghost btn-sm mt-2">Cancel</a>
    </div>

    <script src="https://js.paystack.co/v1/inline.js"></script>
    <script>
        document.getElementById('paystack-btn').addEventListener('click', function () {
            var handler = PaystackPop.setup({
                key: '{{ $paystackKey }}',
                email: '{{ $email }}',
                amount: {{ $amount }},
                ref: '{{ $reference }}',
                metadata: {
                    appointment_id: {{ $appointment->id }},
                },
                callback: function (response) {
                    window.location.href = '/consultation/callback?reference=' + response.reference;
                },
                onClose: function () {
                    // The request stays unpaid and lapses on its own.
                }
            });
            handler.openIframe();
        });
    </script>
</x-layouts.public>
