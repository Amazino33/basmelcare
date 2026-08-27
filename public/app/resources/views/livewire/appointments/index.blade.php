<div>
    <x-header title="Appointments" subtitle="Schedule and manage pharmacy appointments">
        <x-slot:middle class="!justify-end">
            <x-input icon="o-magnifying-glass" placeholder="Search..." wire:model.live.debounce="search" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-select wire:model.live="statusFilter" :options="[
                ['id' => 'requests', 'name' => 'Requests' . ($requestCount ? ' (' . $requestCount . ')' : '')],
                ['id' => 'upcoming', 'name' => 'Upcoming'],
                ['id' => 'scheduled', 'name' => 'Scheduled'],
                ['id' => 'confirmed', 'name' => 'Confirmed'],
                ['id' => 'completed', 'name' => 'Completed'],
                ['id' => 'cancelled', 'name' => 'Cancelled'],
                ['id' => 'no_show', 'name' => 'No Show'],
                ['id' => 'all', 'name' => 'All'],
            ]" option-value="id" option-label="name" class="w-36" />
            <x-button label="New Appointment" wire:click="create" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <!-- Summary -->
    <div class="grid grid-cols-2 gap-4 mb-6">
        <x-stat title="Today" value="{{ $todayCount }}" description="appointments today" icon="o-calendar" color="text-primary" />
        <x-stat title="Upcoming" value="{{ $upcomingCount }}" description="scheduled ahead" icon="o-clock" color="text-info" />
    </div>

    <x-table :headers="$headers" :rows="$appointments" with-pagination>
        @scope('cell_scheduled_at', $appt)
            <div>
                <div class="font-semibold text-sm">{{ $appt->scheduled_at->format('M d, Y') }}</div>
                <div class="text-xs text-base-content/60">{{ $appt->scheduled_at->format('h:i A') }}</div>
            </div>
        @endscope

        @scope('cell_staff.name', $appt)
            {{ $appt->staff?->name ?? '—' }}
        @endscope

        @scope('cell_duration_minutes', $appt)
            {{ $appt->duration_minutes }} min
        @endscope

        @scope('cell_status', $appt)
            @if($appt->status === 'requested')
                {{-- Booked online: the customer asked for a time, someone has to agree it --}}
                <span class="badge badge-warning badge-sm">Requested</span>
                <div class="mt-1">
                    <x-button label="Confirm time" wire:click="updateStatus({{ $appt->id }}, 'confirmed')"
                              class="btn-xs btn-primary" spinner />
                </div>
            @else
                <span class="badge badge-sm">{{ ucfirst(str_replace('_', ' ', $appt->status)) }}</span>
            @endif
            @if($appt->isSelfBooked())
                <div class="text-xs text-base-content/40 mt-1">booked online</div>
            @endif
        @endscope

        @scope('cell_mode', $appt)
            <div class="text-sm">{{ $appt->modeLabel() }}</div>
            <div class="text-xs text-base-content/50">{{ $appt->providerLabel() }}</div>
        @endscope

        @scope('cell_price', $appt)
            @if($appt->was_free)
                <span class="badge badge-success badge-sm">Free</span>
            @else
                <div class="font-semibold tabular-nums">₦{{ number_format($appt->price, 2) }}</div>
                @if($appt->payment_status === 'paid')
                    <span class="badge badge-success badge-xs">Paid</span>
                @else
                    <x-button label="Mark paid" wire:click="markPaid({{ $appt->id }})"
                              class="btn-xs btn-outline btn-warning mt-1" spinner />
                @endif
            @endif
        @endscope

        @scope('cell_status', $appt)
            <x-badge :value="ucfirst(str_replace('_', ' ', $appt->status))" @class([
                'badge-info' => $appt->status === 'scheduled',
                'badge-primary' => $appt->status === 'confirmed',
                'badge-success' => $appt->status === 'completed',
                'badge-error' => $appt->status === 'cancelled',
                'badge-warning' => $appt->status === 'no_show',
            ]) />
        @endscope

        @scope('actions', $appt)
            <div class="flex gap-1">
                @if($appt->status === 'scheduled')
                    <x-button icon="o-check" wire:click="updateStatus({{ $appt->id }}, 'confirmed')" class="btn-xs btn-ghost text-primary" tooltip="Confirm" />
                @endif
                @if(in_array($appt->status, ['scheduled', 'confirmed']))
                    <x-button icon="o-check-circle" wire:click="updateStatus({{ $appt->id }}, 'completed')" class="btn-xs btn-ghost text-success" tooltip="Complete" />
                    <x-button icon="o-x-circle" wire:click="updateStatus({{ $appt->id }}, 'no_show')" class="btn-xs btn-ghost text-warning" tooltip="No Show" />
                @endif
                <x-button icon="o-pencil" wire:click="edit({{ $appt->id }})" class="btn-xs btn-ghost" tooltip="Edit" />
                <x-button icon="o-trash" wire:click="delete({{ $appt->id }})" class="btn-xs btn-ghost text-error" wire:confirm="Delete this appointment?" tooltip="Delete" />
            </div>
        @endscope
    </x-table>

    <x-modal wire:model="modal" title="{{ $appointmentId ? 'Edit Appointment' : 'New Appointment' }}" box-class="max-w-lg">
        <x-form wire:submit="save">
            <x-input label="Title" wire:model="title" placeholder="e.g. Medication Review, Blood Pressure Check" />
            {{-- .live: the charge panel below is gated on $customer_id, and the free
                 allowance cannot be quoted until we know who is being booked. --}}
            <x-choices-offline label="Customer" wire:model.live="customer_id" :options="$customers" option-value="id" option-label="name" placeholder="Search customer..." single searchable />
            <x-choices-offline label="Assigned Staff" wire:model="staff_id" :options="$staff" option-value="id" option-label="name" placeholder="Search staff..." single searchable />
            <div class="grid grid-cols-2 gap-4">
                <x-input label="Date" wire:model="scheduled_date" type="date" />
                <x-input label="Time" wire:model="scheduled_time" type="time" />
            </div>
            <x-input label="Duration (minutes)" wire:model="duration_minutes" type="number" min="5" max="480" />

            <div class="grid grid-cols-2 gap-4">
                <x-select label="How" wire:model.live="mode"
                          :options="collect(\App\Support\ConsultationPricing::MODES)->map(fn($l, $v) => ['id' => $v, 'name' => $l])->values()"
                          option-value="id" option-label="name" />
                <x-select label="With" wire:model.live="provider_type"
                          :options="collect(\App\Support\ConsultationPricing::PROVIDERS)->map(fn($l, $v) => ['id' => $v, 'name' => $l])->values()"
                          option-value="id" option-label="name" />
            </div>

            @if($mode !== 'physical')
                {{-- A call or a chat needs somewhere to reach them; staff arrange it --}}
                <x-input label="Reach them on" wire:model="contact"
                         placeholder="Phone or handle for the {{ $mode === 'video' ? 'call' : 'chat' }}"
                         hint="The system records the mode; you arrange the {{ $mode === 'video' ? 'call' : 'chat' }} yourself." />
            @endif

            @if(! $appointmentId && $customer_id)
                @php
                    $free      = $this->quotedIsFree();
                    $quoted    = $this->quotedPrice();
                    $remaining = $this->freeRemaining();
                @endphp
                <div class="rounded-lg border p-3 text-sm {{ $free ? 'border-success bg-success/5' : 'border-base-300 bg-base-200/40' }}">
                    @if($free)
                        <span class="font-semibold text-success">No charge</span>
                        <span class="text-base-content/70">
                            &mdash; this customer has {{ $remaining }} free
                            {{ Str::plural('consultation', $remaining) }} left.
                        </span>
                    @else
                        <span class="font-semibold">₦{{ number_format($quoted, 2) }}</span>
                        <span class="text-base-content/70">&mdash; payable at the counter.</span>
                    @endif
                </div>
            @endif
            <x-textarea label="Description" wire:model="description" placeholder="What is the appointment for?" rows="2" />
            <x-textarea label="Note" wire:model="appt_note" placeholder="Internal notes" rows="2" />
            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.modal = false" />
                <x-button label="Save" type="submit" class="btn-primary" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
