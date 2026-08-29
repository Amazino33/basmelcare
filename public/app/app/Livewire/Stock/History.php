<?php

namespace App\Livewire\Stock;

use App\Models\StockMovement;
use Livewire\Component;
use Livewire\WithPagination;

class History extends Component
{
    use WithPagination;

    public string $search = '';
    public string $typeFilter = '';

    /** The row opened for detail. */
    public ?int $viewId = null;
    public bool $detailDrawer = false;

    public function viewMovement(int $id): void
    {
        $this->viewId       = $id;
        $this->detailDrawer = true;
    }

    public function closeDetail(): void
    {
        $this->detailDrawer = false;
        $this->viewId       = null;
    }

    public function render()
    {
        $headers = [
            ['key' => 'created_at', 'label' => 'Date'],
            ['key' => 'batch.product.name', 'label' => 'Product'],
            ['key' => 'batch.batch_number', 'label' => 'Batch'],
            ['key' => 'type', 'label' => 'Type'],
            ['key' => 'quantity', 'label' => 'Qty'],
            ['key' => 'reference', 'label' => 'Reference'],
            ['key' => 'from', 'label' => 'From'],
            ['key' => 'to', 'label' => 'To'],
            ['key' => 'user.name', 'label' => 'By'],
        ];

        $movements = StockMovement::with('batch.product', 'fromLocation', 'toLocation', 'user')
            ->when($this->typeFilter, fn($q) => $q->where('type', $this->typeFilter))
            ->when($this->search, fn($q) => $q->whereHas('batch.product', fn($p) => $p->where('name', 'like', "%{$this->search}%")))
            ->latest()
            ->paginate(30);

        $typeOptions = [
            ['id' => '', 'name' => 'All Types'],
            ['id' => 'purchase', 'name' => 'Purchase'],
            ['id' => 'sale', 'name' => 'Sale'],
            ['id' => 'adjustment', 'name' => 'Adjustment'],
            ['id' => 'transfer_in', 'name' => 'Transfer In'],
            ['id' => 'transfer_out', 'name' => 'Transfer Out'],
            ['id' => 'return', 'name' => 'Return'],
        ];

        return view('livewire.stock.history', [
            'headers' => $headers,
            'movements' => $movements,
            'typeOptions' => $typeOptions,
            'viewMovement' => $this->viewId
                ? StockMovement::with('batch.product', 'fromLocation', 'toLocation', 'user')->find($this->viewId)
                : null,
        ]);
    }
}
