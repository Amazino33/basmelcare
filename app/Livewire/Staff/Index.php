<?php

namespace App\Livewire\Staff;

use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast, WithPagination;

    public string $search = '';

    // Form fields
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $password = '';
    public string $role = 'cashier';
    public string $position = '';
    public string $employment_date = '';
    public string $salary = '';
    public string $address = '';
    public string $emergency_contact_name = '';
    public string $emergency_contact_phone = '';
    public string $status = 'active';
    public ?int $staffId = null;
    public bool $modal = false;

    // View details
    public ?int $viewStaffId = null;
    public bool $detailsDrawer = false;

    public function create()
    {
        $this->reset([
            'name', 'email', 'phone', 'password', 'role', 'position',
            'employment_date', 'salary', 'address',
            'emergency_contact_name', 'emergency_contact_phone', 'status', 'staffId',
        ]);
        $this->modal = true;
    }

    public function save()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $this->staffId,
            'phone' => 'nullable|string|max:20',
            'role' => 'required|in:admin,pharmacist,cashier,inventory_manager',
            'position' => 'nullable|string|max:255',
            'employment_date' => 'nullable|date',
            'salary' => 'nullable|numeric|min:0',
            'address' => 'nullable|string|max:500',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'status' => 'required|in:active,inactive,suspended',
        ];

        if (!$this->staffId) {
            $rules['password'] = 'required|string|min:6';
        } else {
            $rules['password'] = 'nullable|string|min:6';
        }

        $this->validate($rules);

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'position' => $this->position,
            'employment_date' => $this->employment_date ?: null,
            'salary' => $this->salary ?: null,
            'address' => $this->address,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'status' => $this->status,
        ];

        if ($this->password) {
            $data['password'] = $this->password;
        }

        if ($this->staffId) {
            User::findOrFail($this->staffId)->update($data);
        } else {
            User::create($data);
        }

        $this->modal = false;
        $this->success($this->staffId ? 'Staff updated.' : 'Staff member added.');
        $this->reset([
            'name', 'email', 'phone', 'password', 'role', 'position',
            'employment_date', 'salary', 'address',
            'emergency_contact_name', 'emergency_contact_phone', 'status', 'staffId',
        ]);
    }

    public function edit($id)
    {
        $staff = User::findOrFail($id);
        $this->staffId = $staff->id;
        $this->name = $staff->name;
        $this->email = $staff->email;
        $this->phone = $staff->phone ?? '';
        $this->password = '';
        $this->role = $staff->role;
        $this->position = $staff->position ?? '';
        $this->employment_date = $staff->employment_date?->format('Y-m-d') ?? '';
        $this->salary = $staff->salary ?? '';
        $this->address = $staff->address ?? '';
        $this->emergency_contact_name = $staff->emergency_contact_name ?? '';
        $this->emergency_contact_phone = $staff->emergency_contact_phone ?? '';
        $this->status = $staff->status;
        $this->modal = true;
    }

    public function viewDetails($id)
    {
        $this->viewStaffId = $id;
        $this->detailsDrawer = true;
    }

    public function toggleStatus($id)
    {
        $staff = User::findOrFail($id);
        $staff->update([
            'status' => $staff->status === 'active' ? 'inactive' : 'active',
        ]);
        $this->success('Staff status updated.');
    }

    public function render()
    {
        $headers = [
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'role', 'label' => 'Role'],
            ['key' => 'phone', 'label' => 'Phone'],
            ['key' => 'employment_date', 'label' => 'Employed'],
            ['key' => 'status', 'label' => 'Status'],
        ];

        $staff = User::query()
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(20);

        $viewStaff = $this->viewStaffId ? User::find($this->viewStaffId) : null;

        return view('livewire.staff.index', [
            'headers' => $headers,
            'staff' => $staff,
            'viewStaff' => $viewStaff,
        ]);
    }
}
