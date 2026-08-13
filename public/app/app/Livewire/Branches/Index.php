<?php

namespace App\Livewire\Branches;

use App\Models\Branch;
use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;

    public string $name = '';
    public string $address = '';
    public string $phone = '';
    public bool $is_main = false;
    public ?int $branchId = null;
    public bool $modal = false;

    public function create()
    {
        $this->reset(['name', 'address', 'phone', 'is_main', 'branchId']);
        $this->modal = true;
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
        ]);

        if ($this->is_main) {
            Branch::where('is_main', true)->update(['is_main' => false]);
        }

        Branch::updateOrCreate(
            ['id' => $this->branchId],
            [
                'name' => $this->name,
                'address' => $this->address,
                'phone' => $this->phone,
                'is_main' => $this->is_main,
            ]
        );

        $this->modal = false;
        $this->success($this->branchId ? 'Branch updated.' : 'Branch created.');
        $this->reset(['name', 'address', 'phone', 'is_main', 'branchId']);
    }

    public function edit($id)
    {
        $branch = Branch::findOrFail($id);
        $this->branchId = $branch->id;
        $this->name = $branch->name;
        $this->address = $branch->address ?? '';
        $this->phone = $branch->phone ?? '';
        $this->is_main = $branch->is_main;
        $this->modal = true;
    }

    public function delete($id)
    {
        $branch = Branch::findOrFail($id);
        if ($branch->users()->count()) {
            $this->error('Cannot delete — branch has assigned staff.');
            return;
        }
        $branch->delete();
        $this->success('Branch deleted.');
    }

    public function render()
    {
        $headers = [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'phone', 'label' => 'Phone'],
            ['key' => 'staff_count', 'label' => 'Staff'],
            ['key' => 'is_main', 'label' => 'Main'],
        ];

        $branches = Branch::withCount('users as staff_count')->get();

        return view('livewire.branches.index', [
            'headers' => $headers,
            'branches' => $branches,
        ]);
    }
}
