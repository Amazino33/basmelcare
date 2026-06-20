<?php

namespace App\Livewire\Appointments;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\User;
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
    public ?int $appointmentId = null;
    public bool $modal = false;

    public function create()
    {
        $this->reset(['customer_id', 'staff_id', 'title', 'description', 'scheduled_date', 'scheduled_time', 'duration_minutes', 'appt_note', 'appointmentId']);
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
        ]);

        $scheduledAt = $this->scheduled_date . ' ' . $this->scheduled_time;

        Appointment::updateOrCreate(
            ['id' => $this->appointmentId],
            [
                'customer_id' => $this->customer_id,
                'user_id' => $this->staff_id,
                'title' => $this->title,
                'description' => $this->description,
                'scheduled_at' => $scheduledAt,
                'duration_minutes' => $this->duration_minutes,
                'note' => $this->appt_note,
            ]
        );

        $this->modal = false;
        $this->success($this->appointmentId ? 'Appointment updated.' : 'Appointment scheduled.');
        $this->reset(['customer_id', 'staff_id', 'title', 'description', 'scheduled_date', 'scheduled_time', 'duration_minutes', 'appt_note', 'appointmentId']);
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
        $this->modal = true;
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
            ['key' => 'status', 'label' => 'Status'],
        ];

        $query = Appointment::with('customer', 'staff')
            ->when($this->search, fn($q) => $q->where('title', 'like', "%{$this->search}%")
                ->orWhereHas('customer', fn($c) => $c->where('name', 'like', "%{$this->search}%")));

        if ($this->statusFilter === 'upcoming') {
            $query->where('scheduled_at', '>=', now())->whereIn('status', ['scheduled', 'confirmed']);
        } elseif ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $appointments = $query->orderBy('scheduled_at')->paginate(20);

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
            'customers' => $customers,
            'staff' => $staff,
        ]);
    }
}
