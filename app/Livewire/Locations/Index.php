<?php

namespace App\Livewire\Locations;

use App\Models\Location;
use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;

    public string $name = '';
    public string $type = 'shop';
    public string $address = '';
    public bool $is_default = false;
    public ?int $locationId = null;
    public bool $modal = false;

    public function create()
    {
        $this->reset(['name', 'type', 'address', 'is_default', 'locationId']);
        $this->modal = true;
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:shop,warehouse,storage',
            'address' => 'nullable|string|max:500',
        ]);

        if ($this->is_default) {
            Location::where('is_default', true)->update(['is_default' => false]);
        }

        Location::updateOrCreate(
            ['id' => $this->locationId],
            [
                'name' => $this->name,
                'type' => $this->type,
                'address' => $this->address,
                'is_default' => $this->is_default,
            ]
        );

        $this->modal = false;
        $this->success($this->locationId ? 'Location updated.' : 'Location created.');
        $this->reset(['name', 'type', 'address', 'is_default', 'locationId']);
    }

    public function edit($id)
    {
        $location = Location::findOrFail($id);
        $this->locationId = $location->id;
        $this->name = $location->name;
        $this->type = $location->type;
        $this->address = $location->address ?? '';
        $this->is_default = $location->is_default;
        $this->modal = true;
    }

    public function delete($id)
    {
        $location = Location::findOrFail($id);
        if ($location->batches()->where('quantity', '>', 0)->exists()) {
            $this->error('Cannot delete — location has stock.');
            return;
        }
        $location->delete();
        $this->success('Location deleted.');
    }

    public function render()
    {
        $headers = [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'type', 'label' => 'Type'],
            ['key' => 'stock_count', 'label' => 'Stock'],
            ['key' => 'is_default', 'label' => 'Default'],
        ];

        $locations = Location::withCount(['batches as stock_count' => fn($q) => $q->selectRaw('COALESCE(SUM(quantity), 0)')])->get();

        return view('livewire.locations.index', [
            'headers' => $headers,
            'locations' => $locations,
        ]);
    }
}
