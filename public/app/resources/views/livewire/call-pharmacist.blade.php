{{-- Polled, not pushed: no realtime here, and the till already refreshes this
     way. Five seconds is quick enough for somebody walking across a shop. --}}
<div wire:poll.5s
     x-data="pharmacistCall({{ $waiting?->id ?: 'null' }})"
     x-effect="announce({{ $waiting?->id ?: 'null' }})">

    @if($waiting)
        {{-- The pharmacist, on whatever page they are on --}}
        <div class="alert alert-warning shadow-lg mb-3 py-2">
            <x-icon name="o-bell-alert" class="w-5 h-5 shrink-0 animate-pulse" />
            <div class="flex-1 min-w-0">
                <div class="font-semibold text-sm">A customer is waiting</div>
                <div class="text-xs opacity-80">
                    Called by {{ $waiting->caller->name ?? 'the counter' }}
                    &middot; {{ $waiting->created_at->diffForHumans() }}
                </div>
            </div>
            <x-button label="On my way" icon="o-check"
                      wire:click="acknowledge({{ $waiting->id }})"
                      class="btn-sm" spinner="acknowledge({{ $waiting->id }})" />
        </div>
    @endif

    @if($this->canRespond())
        {{-- Sound is blocked until the page has been interacted with, and a
             desktop notification needs asking. Both are one tap, offered once. --}}
        <div x-show="needsPermission" x-cloak class="alert alert-info py-2 mb-3 text-sm gap-2">
            <x-icon name="o-speaker-wave" class="w-4 h-4 shrink-0" />
            <span class="flex-1">Turn on the alert so you hear the counter calling.</span>
            <button type="button" class="btn btn-sm" @click="enable()">Turn on</button>
        </div>
    @endif

    @if($this->canCall())
        @if($mine && ! $mine->acknowledged_at && $mine->created_at->gt(now()->subMinutes(15)))
            {{-- Called, nobody has answered yet. Shown so they do not press
                 again wondering whether it worked. --}}
            <div class="alert alert-info py-2 mb-3 text-sm gap-2">
                <span class="loading loading-spinner loading-xs"></span>
                <span>Pharmacist called {{ $mine->created_at->diffForHumans() }} &mdash; waiting for someone to answer.</span>
            </div>
        @elseif($mine && $mine->acknowledged_at && $mine->acknowledged_at->gt(now()->subMinutes(2)))
            <div class="alert alert-success py-2 mb-3 text-sm gap-2">
                <x-icon name="o-check-circle" class="w-4 h-4 shrink-0" />
                <span>{{ $mine->acknowledgedBy->name ?? 'A pharmacist' }} is on the way.</span>
            </div>
        @else
            <x-button label="Call pharmacist" icon="o-bell"
                      wire:click="callPharmacist" spinner="callPharmacist"
                      class="btn-sm btn-outline btn-warning" />
        @endif
    @endif
</div>

@script
<script>
    Alpine.data('pharmacistCall', (initialId) => ({
        // Whatever was already on screen when this loaded is not new, so it
        // does not sound. Only a call that arrives while watching does.
        lastAnnounced: initialId,
        needsPermission: false,

        init() {
            this.needsPermission = this.canAskForNotifications();
        },

        canAskForNotifications() {
            return 'Notification' in window && Notification.permission === 'default';
        },

        async enable() {
            // Both need a real click. Browsers refuse to make noise or show a
            // notification for a page the person has not interacted with, which
            // is why this is a button rather than something done on load.
            this.beep();

            if ('Notification' in window) {
                try { await Notification.requestPermission(); } catch (e) { /* declined */ }
            }

            this.needsPermission = this.canAskForNotifications();
        },

        announce(id) {
            if (!id || id === this.lastAnnounced) return;

            this.lastAnnounced = id;
            this.beep();
            this.notify();
        },

        /**
         * A two-tone chime built in the browser.
         *
         * No audio file on purpose: nothing to host, nothing to load, and it
         * still works on a slow connection or with the CDN unreachable.
         */
        beep() {
            try {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) return;

                const ctx = new Ctx();
                [880, 1175].forEach((freq, i) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.value = freq;
                    osc.connect(gain);
                    gain.connect(ctx.destination);

                    const at = ctx.currentTime + i * 0.18;
                    gain.gain.setValueAtTime(0.0001, at);
                    gain.gain.exponentialRampToValueAtTime(0.35, at + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, at + 0.16);
                    osc.start(at);
                    osc.stop(at + 0.18);
                });
            } catch (e) {
                // No audio available. The banner is still on screen.
            }
        },

        /**
         * A desktop notification, so it lands even when this tab is not the
         * one being looked at. Silent: the chime above already sounded, and
         * two noises at once is worse than one.
         */
        notify() {
            if (!('Notification' in window) || Notification.permission !== 'granted') return;

            try {
                new Notification('A customer is waiting', {
                    body: 'The counter is asking for a pharmacist.',
                    icon: '/logo.png',
                    tag: 'pharmacist-call',
                    silent: true,
                });
            } catch (e) {
                // Some browsers refuse outside a service worker; the sound and
                // the banner have already done their job.
            }
        },
    }));
</script>
@endscript
