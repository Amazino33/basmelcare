<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use App\Models\MedicalRecord;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast, WithPagination, WithFileUploads;

    public string $search = '';
    public string $name = '';
    public string $type = 'retail';
    public string $phone = '';
    public string $email = '';
    public string $address = '';
    public string $notes = '';
    public ?int $customerId = null;
    public bool $modal = false;

    // Customer profile drawer
    public ?int $viewCustomerId = null;
    public bool $profileDrawer = false;

    // Medical record form
    public string $mr_title = '';
    public string $mr_type = 'prescription';
    public string $mr_details = '';
    public string $mr_date = '';
    public string $mr_note = '';
    public $mr_file = null;
    public bool $mrModal = false;

    public function create()
    {
        $this->reset(['name', 'type', 'phone', 'email', 'address', 'notes', 'customerId']);
        $this->modal = true;
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:retail,wholesale',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        Customer::updateOrCreate(
            ['id' => $this->customerId],
            [
                'name' => $this->name,
                'type' => $this->type,
                'phone' => $this->phone,
                'email' => $this->email,
                'address' => $this->address,
                'notes' => $this->notes,
            ]
        );

        $this->modal = false;
        $this->success($this->customerId ? 'Customer updated.' : 'Customer created.');
        $this->reset(['name', 'type', 'phone', 'email', 'address', 'notes', 'customerId']);
    }

    public function edit($id)
    {
        $customer = Customer::findOrFail($id);
        $this->customerId = $customer->id;
        $this->name = $customer->name;
        $this->type = $customer->type;
        $this->phone = $customer->phone ?? '';
        $this->email = $customer->email ?? '';
        $this->address = $customer->address ?? '';
        $this->notes = $customer->notes ?? '';
        $this->modal = true;
    }

    public function delete($id)
    {
        Customer::findOrFail($id)->delete();
        $this->success('Customer deleted.');
    }

    public function viewProfile($id)
    {
        $this->viewCustomerId = $id;
        $this->profileDrawer = true;
    }

    public function openMedicalRecord()
    {
        $this->reset(['mr_title', 'mr_type', 'mr_details', 'mr_date', 'mr_note', 'mr_file']);
        $this->mr_date = now()->format('Y-m-d');
        $this->mrModal = true;
    }

    public function saveMedicalRecord()
    {
        $this->validate([
            'mr_title' => 'required|string|max:255',
            'mr_type' => 'required|string|max:100',
            'mr_details' => 'nullable|string',
            'mr_date' => 'required|date',
            'mr_note' => 'nullable|string',
            'mr_file' => 'nullable|file|max:5120',
        ]);

        $filePath = $this->mr_file?->store('medical-records', 'public');

        MedicalRecord::create([
            'customer_id' => $this->viewCustomerId,
            'recorded_by' => auth()->id(),
            'title' => $this->mr_title,
            'type' => $this->mr_type,
            'details' => $this->mr_details,
            'record_date' => $this->mr_date,
            'file_path' => $filePath,
            'note' => $this->mr_note,
        ]);

        $this->mrModal = false;
        $this->success('Medical record added.');
        $this->reset(['mr_title', 'mr_type', 'mr_details', 'mr_date', 'mr_note', 'mr_file']);
    }

    public function deleteMedicalRecord($id)
    {
        MedicalRecord::findOrFail($id)->delete();
        $this->success('Medical record deleted.');
    }

    public function render()
    {
        $headers = [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'type', 'label' => 'Type'],
            ['key' => 'phone', 'label' => 'Phone'],
            ['key' => 'email', 'label' => 'Email'],
        ];

        $viewCustomer = $this->viewCustomerId
            ? Customer::with(['medicalRecords' => fn($q) => $q->latest(), 'medicalRecords.recorder', 'sales' => fn($q) => $q->latest()->limit(10), 'debts' => fn($q) => $q->whereIn('status', ['unpaid', 'partial'])])->find($this->viewCustomerId)
            : null;

        return view('livewire.customers.index', [
            'headers' => $headers,
            'customers' => Customer::when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))->latest()->paginate(20),
            'viewCustomer' => $viewCustomer,
        ]);
    }
}
