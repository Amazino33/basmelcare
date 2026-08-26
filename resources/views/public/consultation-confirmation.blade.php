<x-layouts.public title="Consultation booked">
    <div class="max-w-md mx-auto px-4 py-12 text-center">
        <div class="text-success mb-3">
            <x-icon name="o-check-circle" class="w-14 h-14 mx-auto" />
        </div>

        <h1 class="text-xl font-bold mb-2">Consultation booked</h1>

        <p class="text-base-content/70 text-sm">
            {{ $appointment->modeLabel() }} with a {{ strtolower($appointment->providerLabel()) }},
            requested for {{ $appointment->scheduled_at->format('D, d M') }} at
            {{ $appointment->scheduled_at->format('g:i A') }}.
        </p>

        @if($appointment->was_free)
            <div class="badge badge-success mt-4">Free consultation</div>
        @else
            <p class="text-sm mt-3">Paid &#8358;{{ number_format($appointment->price, 2) }}</p>
        @endif

        <div class="rounded-lg border border-base-200 bg-base-200/40 p-4 text-sm text-left mt-6">
            <p class="font-semibold mb-1">What happens next</p>
            <p class="text-base-content/70">
                We will call you on <strong>{{ $appointment->contact }}</strong> to confirm the
                time, or suggest another if that one is taken.
                @if($appointment->mode !== 'physical')
                    The {{ $appointment->mode === 'video' ? 'call' : 'chat' }} itself will be
                    arranged on that number.
                @endif
            </p>
        </div>

        <a href="{{ route('shop.index') }}" class="btn btn-outline btn-sm mt-6">Back to the shop</a>
    </div>
</x-layouts.public>
