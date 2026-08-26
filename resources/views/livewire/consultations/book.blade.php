<div class="max-w-2xl mx-auto px-4 py-8">

    @if($booked)
        {{-- Free consultations never touch Paystack, so this is where they land --}}
        <div class="card bg-base-100 border border-success/30 p-6 text-center">
            <div class="text-success mb-3">
                <x-icon name="o-check-circle" class="w-14 h-14 mx-auto" />
            </div>
            <h1 class="text-xl font-bold mb-2">Request received</h1>
            <p class="text-base-content/70 text-sm">
                Your consultation is <strong>free</strong>. We will call you to confirm
                {{ $booked->scheduled_at->format('D, d M') }} at
                {{ $booked->scheduled_at->format('g:i A') }}, or suggest another time.
            </p>
            <div class="text-sm mt-4 text-base-content/60">
                {{ $booked->modeLabel() }} with a {{ strtolower($booked->providerLabel()) }}
            </div>
            <a href="{{ route('shop.index') }}" class="btn btn-outline btn-sm mt-5">Back to the shop</a>
        </div>
    @else
        <h1 class="text-2xl font-bold">Book a consultation</h1>
        <p class="text-base-content/60 text-sm mt-1 mb-6">
            Speak to a pharmacist in person, by video call, or by message.
        </p>

        <form wire:submit="book" class="space-y-4">
            <div class="card bg-base-100 border border-base-200 p-4 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-input label="Your name" wire:model="name" placeholder="Ada Okafor" />
                    <x-input label="Phone number" wire:model.live.debounce.600ms="phone" placeholder="0803 123 4567"
                             hint="We use this to find your record" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-select label="How would you like it?" wire:model.live="mode"
                              :options="collect(\App\Support\ConsultationPricing::MODES)->map(fn($l, $v) => ['id' => $v, 'name' => $l])->values()"
                              option-value="id" option-label="name" />
                    <x-select label="With" wire:model.live="provider_type"
                              :options="collect(\App\Support\ConsultationPricing::PROVIDERS)->map(fn($l, $v) => ['id' => $v, 'name' => $l])->values()"
                              option-value="id" option-label="name" />
                </div>

                @if($mode !== 'physical')
                    <x-input label="{{ $mode === 'video' ? 'Number or handle for the call' : 'Number for the chat' }}"
                             wire:model="contact"
                             hint="A pharmacist will contact you on this at the agreed time." />
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-input label="Preferred date" wire:model="preferred_date" type="date"
                             min="{{ now()->format('Y-m-d') }}" />
                    <x-input label="Preferred time" wire:model="preferred_time" type="time" />
                </div>

                <x-textarea label="What is it about?" wire:model="about" rows="3"
                            placeholder="Optional — it helps the pharmacist prepare" />
            </div>

            {{-- Only once there is a phone number: the free allowance is per
                 customer, so it cannot be known before we know who this is. --}}
            @if(trim($phone) !== '')
                @php
                    $free      = $this->isFree();
                    $charge    = $this->charge();
                    $remaining = $this->freeRemaining();
                @endphp

                <div class="card border p-4 {{ $free ? 'border-success bg-success/5' : 'border-base-300 bg-base-200/40' }}">
                    @if($free)
                        <div class="font-bold text-success">No charge</div>
                        <div class="text-sm text-base-content/70 mt-1">
                            You have {{ $remaining }} free {{ Str::plural('consultation', $remaining) }}.
                            We will call you to confirm the time.
                        </div>
                    @else
                        <div class="flex items-baseline justify-between">
                            <span class="text-sm text-base-content/70">To pay</span>
                            <span class="text-xl font-bold text-primary">₦{{ number_format($charge, 2) }}</span>
                        </div>
                        <div class="text-xs text-base-content/60 mt-2">
                            Paid now to hold the request. We will call you to confirm the time,
                            or suggest another if that one is taken.
                        </div>
                    @endif
                </div>
            @endif

            <x-button type="submit" class="btn-primary btn-block" spinner="book"
                      label="{{ trim($phone) !== '' && ! $this->isFree() ? 'Continue to payment' : 'Request this consultation' }}" />

            <p class="text-xs text-base-content/50 text-center">
                We will contact you to agree the time. Video calls and chats are arranged by
                our staff on the number you give above.
            </p>
        </form>
    @endif
</div>
