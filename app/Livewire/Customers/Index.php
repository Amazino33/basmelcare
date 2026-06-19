<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast, WithPagination;

    public string $search = '';
    public string $name = '';
    public string $type = 'retail';
    public string $phone = '';
    public string $email = '';
    public string $address = '';
    public string $notes = '';
    public ?int $customerId = null;
    public bool $modal = false;

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

    public function render()
    {
        $headers = [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'type', 'label' => 'Type'],
            ['key' => 'phone', 'label' => 'Phone'],
            ['key' => 'email', 'label' => 'Email'],
        ];

        return view('livewire.customers.index', [
            'headers' => $headers,
            'customers' => Customer::when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))->latest()->paginate(20),
        ]);
    }
}
