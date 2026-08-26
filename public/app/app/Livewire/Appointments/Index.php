<?php

namespace App\Livewire\Appointments;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\User;
use App\Support\ConsultationPricing;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast, WithPagination;

    public string $search = '';
    public string $statusFilter = 'upcoming';

    // Form
    public ?int $customer_id = null;
    public ?int $staff_id = null;
    public string $title = '';
    public string $description = '';
    public string $scheduled_date = '';
    public string $scheduled_time = '';
    public int $duration_minutes = 30;
    public string $appt_note = '';

    // Consultation
    public string $mode = 'physical';
    public string $provider_type = 'pharmacist';
    public string $contact = '';
    public ?int $appointmentId = null;
    public bool $modal = false;

    public function create()
    {
        $this->reset(['customer_id', 'staff_id', 'title', 'description', 'scheduled_date', 'scheduled_time', 'duration_minutes', 'appt_note', 'appointmentId', 'mode', 'provider_type', 'contact']);
        $this->duration_minutes = 30;
        $this->modal = true;
    }

    public function save()
    {
        $this->validate([
            'customer_id' => 'required|exists:customers,id',
            'staff_id' => 'nullable|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'scheduled_date' => 'required|date',
            'scheduled_time' => 'required',
            'duration_minutes' => 'required|integer|min:5|max:480',
            'appt_note' => 'nullable|string',
            'mode' => 'required|in:' . implode(',', array_keys(ConsultationPricing::MODES)),
            'provider_type' => 'required|in:' . implode(',', array_keys(ConsultationPricing::PROVIDERS)),
            // A video call or a chat needs somewhere to reach them; an in-person
            // visit does not.
            'contact' => 'nullable|string|max:100|required_unless:mode,physical',
        ]);

        $scheduledAt = $this->scheduled_date . ' ' . $this->scheduled_time;

        $attributes = [
            'customer_id'      => $this->customer_id,
            'user_id'          => $this->staff_id,
            'title'            => $this->title,
            'description'      => $this->description,
            'scheduled_at'     => $scheduledAt,
            'duration_minutes' => $this->duration_minutes,
            'note'             => $this->appt_note,
            'mode'             => $this->mode,
            'provider_type'    => $this->provider_type,
            'contact'          => $this->contact ?: null,
        ];

        // Price only when creating. Re-pricing an existing appointment on every
        // edit would change what a customer was quoted, and could take away a
        // free one they were already promised.
        if (! $this->appointmentId) {
            $customer = Customer::find($this->customer_id);
            $free     = ConsultationPricing::isFreeFor($customer);

            $attributes['was_free']       = $free;
            $attributes['price']          = $free ? 0 : ConsultationPricing::price($this->provider_type, $this->mode);
            $attributes['payment_status'] = $free ? 'free' : 'pending';
            $attributes['booked_by']      = auth()->id();
        }

        Appointment::updateOrCreate(['id' => $this->appointmentId], $attributes);

        $this->modal = false;
        $this->success($this->appointmentId ? 'Appointment updated.' : 'Appointment scheduled.');
        $this->reset(['customer_id', 'staff_id', 'title', 'description', 'scheduled_date', 'scheduled_time', 'duration_minutes', 'appt_note', 'appointmentId', 'mode', 'provider_type', 'contact']);
    }

    public function edit($id)
    {
        $appt = Appointment::findOrFail($id);
        $this->appointmentId = $appt->id;
        $this->customer_id = $appt->customer_id;
        $this->staff_id = $appt->user_id;
        $this->title = $appt->title;
        $this->description = $appt->description ?? '';
        $this->scheduled_date = $appt->scheduled_at->format('Y-m-d');
        $this->scheduled_time = $appt->scheduled_at->format('H:i');
        $this->duration_minutes = $appt->duration_minutes;
        $this->appt_note = $appt->note ?? '';
        $this->mode = $appt->mode ?? 'physical';
        $this->provider_type = $appt->provider_type ?? 'pharmacist';
        $this->contact = $appt->contact ?? '';
        $this->modal = true;
    }

    /**
     * Records that the customer has paid, at the counter.
     *
     * No money moves here - the till is where that happens. This marks the
     * consultation settled so it stops showing as owing.
     */
    public function markPaid(int $id): void
    {
        $appointment = Appointment::findOrFail($id);

        if ($appointment->isSettled()) {
            $this->error('This consultation is already settled.');

            return;
        }

        $appointment->update([
            'payment_status' => 'paid',
            'paid_at'        => now(),
        ]);

        $this->success('Consultation marked paid.');
    }

    /** What this customer would be charged for the options currently chosen. */
    public function quotedPrice(): float
    {
        return ConsultationPricing::chargeFor(
            Customer::find($this->customer_id),
            $this->provider_type,
            $this->mode
        );
    }

    public function quotedIsFree(): bool
    {
        return ConsultationPricing::isFreeFor(Customer::find($this->customer_id));
    }

    public function freeRemaining(): int
    {
        return ConsultationPricing::freeRemainingFor(Customer::find($this->customer_id));
    }

    public function updateStatus($id, $status)
    {
        Appointment::findOrFail($id)->update(['status' => $status]);
        $this->success('Appointment ' . $status . '.');
    }

    public function delete($id)
    {
        Appointment::findOrFail($id)->delete();
        $this->success('Appointment deleted.');
    }

    public function render()
    {
        $headers = [
            ['key' => 'scheduled_at', 'label' => 'Date & Time'],
            ['key' => 'title', 'label' => 'Title'],
            ['key' => 'customer.name', 'label' => 'Customer'],
            ['key' => 'staff.name', 'label' => 'Staff'],
            ['key' => 'duration_minutes', 'label' => 'Duration'],
            ['key' => 'mode', 'label' => 'How'],
            ['key' => 'price', 'label' => 'Charge'],
            ['key' => 'status', 'label' => 'Status'],
        ];

        $query = Appointment::with('customer', 'staff')
            ->when($this->search, fn($q) => $q->where('title', 'like', "%{$this->search}%")
                ->orWhereHas('customer', fn($c) => $c->where('name', 'like', "%{$this->search}%")));

        // A booking the customer made online and never paid for stops counting
        // as live after the hold window. It is not deleted - the record shows
        // someone tried and gave up - but staff should not be chasing it.
        $notLapsed = fn ($q) => $q->where(fn ($inner) => $inner
            ->where('payment_status', '!=', 'pending')
            ->orWhereNotNull('booked_by')
            ->orWhere('created_at', '>=', now()->subMinutes(ConsultationPricing::holdMinutes())));

        if ($this->statusFilter === 'requests') {
            // Booked online and waiting on someone to agree the time.
            $query->where('status', 'requested')->where($notLapsed);
        } elseif ($this->statusFilter === 'upcoming') {
            $query->where('scheduled_at', '>=', now())
                ->whereIn('status', ['requested', 'scheduled', 'confirmed'])
                ->where($notLapsed);
        } elseif ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $appointments = $query->orderBy('scheduled_at')->paginate(20);

        $requestCount = Appointment::where('status', 'requested')
            ->where(fn ($q) => $q
                ->where('payment_status', '!=', 'pending')
                ->orWhereNotNull('booked_by')
                ->orWhere('created_at', '>=', now()->subMinutes(ConsultationPricing::holdMinutes())))
            ->count();

        $todayCount = Appointment::whereDate('scheduled_at', today())
            ->whereIn('status', ['scheduled', 'confirmed'])->count();

        $upcomingCount = Appointment::where('scheduled_at', '>=', now())
            ->whereIn('status', ['scheduled', 'confirmed'])->count();

        $customers = Customer::orderBy('name')->get();
        $staff = User::where('status', 'active')->orderBy('name')->get();

        return view('livewire.appointments.index', [
            'headers' => $headers,
            'appointments' => $appointments,
            'todayCount' => $todayCount,
            'upcomingCount' => $upcomingCount,
            'requestCount' => $requestCount,
            'customers' => $customers,
            'staff' => $staff,
        ]);
    }
}
