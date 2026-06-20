<?php

namespace App\Livewire\Stock;

use App\Models\Batch;
use App\Models\Product;
use App\Models\StockMovement;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Adjustments extends Component
{
    use Toast, WithPagination;

    public ?int $product_id = null;
    public ?int $batch_id = null;
    public string $adjustment_type = 'add';
    public string $reason = '';
    public int $adjust_qty = 0;
    public string $adjust_note = '';
    public bool $modal = false;

    public function openAdjustment()
    {
        $this->reset(['product_id', 'batch_id', 'adjustment_type', 'reason', 'adjust_qty', 'adjust_note']);
        $this->modal = true;
    }

    public function updatedProductId()
    {
        $this->batch_id = null;
    }

    public function adjust()
    {
        $this->validate([
            'product_id' => 'required|exists:products,id',
            'batch_id' => 'required|exists:batches,id',
            'adjustment_type' => 'required|in:add,remove',
            'reason' => 'required|string|max:255',
            'adjust_qty' => 'required|integer|min:1',
            'adjust_note' => 'nullable|string|max:500',
        ]);

        $batch = Batch::findOrFail($this->batch_id);

        if ($this->adjustment_type === 'remove' && $this->adjust_qty > $batch->quantity) {
            $this->error('Cannot remove more than available stock (' . $batch->quantity . ').');
            return;
        }

        $qty = $this->adjustment_type === 'add' ? $this->adjust_qty : -$this->adjust_qty;

        if ($this->adjustment_type === 'add') {
            $batch->increment('quantity', $this->adjust_qty);
        } else {
            $batch->decrement('quantity', $this->adjust_qty);
        }

        StockMovement::create([
            'batch_id' => $batch->id,
            'quantity' => $qty,
            'type' => 'adjustment',
            'reference' => $this->reason,
            'note' => $this->adjust_note,
            'from_location_id' => $batch->location_id,
            'to_location_id' => $batch->location_id,
            'user_id' => auth()->id(),
        ]);

        $this->modal = false;
        $this->success('Stock adjusted: ' . ($this->adjustment_type === 'add' ? '+' : '-') . $this->adjust_qty . ' units.');
        $this->reset(['product_id', 'batch_id', 'adjustment_type', 'reason', 'adjust_qty', 'adjust_note']);
    }

    public function render()
    {
        $products = Product::orderBy('name')->get();

        $batches = $this->product_id
            ? Batch::where('product_id', $this->product_id)
                ->with('location')
                ->get()
                ->map(fn($b) => [
                    'id' => $b->id,
                    'name' => $b->batch_number . ' — ' . ($b->location?->name ?? 'No location') . ' (' . $b->quantity . ' units)',
                ])
            : collect();

        $reasons = [
            ['id' => 'Damaged goods', 'name' => 'Damaged goods'],
            ['id' => 'Expired disposal', 'name' => 'Expired disposal'],
            ['id' => 'Customer return', 'name' => 'Customer return'],
            ['id' => 'Stock count correction', 'name' => 'Stock count correction'],
            ['id' => 'Theft/loss', 'name' => 'Theft/loss'],
            ['id' => 'Supplier return', 'name' => 'Supplier return'],
            ['id' => 'Other', 'name' => 'Other'],
        ];

        $headers = [
            ['key' => 'created_at', 'label' => 'Date'],
            ['key' => 'batch.product.name', 'label' => 'Product'],
            ['key' => 'batch.batch_number', 'label' => 'Batch'],
            ['key' => 'quantity', 'label' => 'Qty'],
            ['key' => 'reference', 'label' => 'Reason'],
            ['key' => 'user.name', 'label' => 'By'],
        ];

        $adjustments = StockMovement::with('batch.product', 'user')
            ->where('type', 'adjustment')
            ->latest()
            ->paginate(20);

        return view('livewire.stock.adjustments', [
            'products' => $products,
            'batches' => $batches,
            'reasons' => $reasons,
            'headers' => $headers,
            'adjustments' => $adjustments,
        ]);
    }
}
