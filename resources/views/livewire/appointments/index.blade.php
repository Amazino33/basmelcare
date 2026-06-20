<div>
    <x-header title="Appointments" subtitle="Schedule and manage pharmacy appointments">
        <x-slot:middle class="!justify-end">
            <x-input icon="o-magnifying-glass" placeholder="Search..." wire:model.live.debounce="search" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-select wire:model.live="statusFilter" :options="[
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
            <x-select label="Customer" wire:model="customer_id" :options="$customers" option-value="id" option-label="name" placeholder="Select customer" />
            <x-select label="Assigned Staff" wire:model="staff_id" :options="$staff" option-value="id" option-label="name" placeholder="Optional" />
            <div class="grid grid-cols-2 gap-4">
                <x-input label="Date" wire:model="scheduled_date" type="date" />
                <x-input label="Time" wire:model="scheduled_time" type="time" />
            </div>
            <x-input label="Duration (minutes)" wire:model="duration_minutes" type="number" min="5" max="480" />
            <x-textarea label="Description" wire:model="description" placeholder="What is the appointment for?" rows="2" />
            <x-textarea label="Note" wire:model="appt_note" placeholder="Internal notes" rows="2" />
            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.modal = false" />
                <x-button label="Save" type="submit" class="btn-primary" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
