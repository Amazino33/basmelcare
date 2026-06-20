<?php

namespace App\Livewire\Suppliers;

use App\Models\Supplier;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast, WithPagination;

    public string $search = '';
    public string $name = '';
    public string $phone = '';
    public string $email = '';
    public string $address = '';
    public string $contact_person = '';
    public ?int $supplierId = null;
    public bool $modal = false;

    public ?int $viewSupplierId = null;
    public bool $profileDrawer = false;

    public function create()
    {
        $this->reset(['name', 'phone', 'email', 'address', 'contact_person', 'supplierId']);
        $this->modal = true;
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'contact_person' => 'nullable|string|max:255',
        ]);

        Supplier::updateOrCreate(
            ['id' => $this->supplierId],
            [
                'name' => $this->name,
                'phone' => $this->phone,
                'email' => $this->email,
                'address' => $this->address,
                'contact_person' => $this->contact_person,
            ]
        );

        $this->modal = false;
        $this->success($this->supplierId ? 'Supplier updated.' : 'Supplier created.');
        $this->reset(['name', 'phone', 'email', 'address', 'contact_person', 'supplierId']);
    }

    public function edit($id)
    {
        $supplier = Supplier::findOrFail($id);
        $this->supplierId = $supplier->id;
        $this->name = $supplier->name;
        $this->phone = $supplier->phone ?? '';
        $this->email = $supplier->email ?? '';
        $this->address = $supplier->address ?? '';
        $this->contact_person = $supplier->contact_person ?? '';
        $this->modal = true;
    }

    public function delete($id)
    {
        Supplier::findOrFail($id)->delete();
        $this->success('Supplier deleted.');
    }

    public function viewProfile($id)
    {
        $this->viewSupplierId = $id;
        $this->profileDrawer = true;
    }

    public function render()
    {
        $headers = [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'contact_person', 'label' => 'Contact Person'],
            ['key' => 'phone', 'label' => 'Phone'],
            ['key' => 'email', 'label' => 'Email'],
        ];

        $viewSupplier = $this->viewSupplierId
            ? Supplier::with(['purchaseOrders' => fn($q) => $q->latest()->limit(10)])->find($this->viewSupplierId)
            : null;

        return view('livewire.suppliers.index', [
            'headers' => $headers,
            'suppliers' => Supplier::when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))->latest()->paginate(20),
            'viewSupplier' => $viewSupplier,
        ]);
    }
}
