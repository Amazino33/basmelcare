{{-- Polled, not pushed: no realtime here, and the till already refreshes this
     way. Five seconds is quick enough for somebody walking across a shop. --}}
<div wire:poll.5s>

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
                      wire:click="call" spinner="call"
                      class="btn-sm btn-outline btn-warning" />
        @endif
    @endif
</div>
