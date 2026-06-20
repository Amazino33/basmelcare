<?php

namespace App\Livewire\Stock;

use App\Models\Batch;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Transfers extends Component
{
    use Toast, WithPagination;

    public ?int $product_id = null;
    public ?int $batch_id = null;
    public ?int $from_location_id = null;
    public ?int $to_location_id = null;
    public int $transfer_qty = 0;
    public string $transfer_note = '';
    public bool $modal = false;

    public function openTransfer()
    {
        $this->reset(['product_id', 'batch_id', 'from_location_id', 'to_location_id', 'transfer_qty', 'transfer_note']);
        $this->modal = true;
    }

    public function updatedProductId()
    {
        $this->batch_id = null;
        $this->from_location_id = null;
    }

    public function updatedBatchId()
    {
        if ($this->batch_id) {
            $batch = Batch::find($this->batch_id);
            $this->from_location_id = $batch?->location_id;
        }
    }

    public function transfer()
    {
        $this->validate([
            'product_id' => 'required|exists:products,id',
            'batch_id' => 'required|exists:batches,id',
            'from_location_id' => 'required|exists:locations,id',
            'to_location_id' => 'required|exists:locations,id|different:from_location_id',
            'transfer_qty' => 'required|integer|min:1',
            'transfer_note' => 'nullable|string|max:500',
        ]);

        $sourceBatch = Batch::findOrFail($this->batch_id);

        if ($this->transfer_qty > $sourceBatch->quantity) {
            $this->error('Transfer quantity exceeds available stock (' . $sourceBatch->quantity . ').');
            return;
        }

        DB::transaction(function () use ($sourceBatch) {
            $sourceBatch->decrement('quantity', $this->transfer_qty);

            $destBatch = Batch::firstOrCreate(
                [
                    'product_id' => $sourceBatch->product_id,
                    'batch_number' => $sourceBatch->batch_number,
                    'location_id' => $this->to_location_id,
                ],
                [
                    'expiry_date' => $sourceBatch->expiry_date,
                    'cost_price' => $sourceBatch->cost_price,
                    'quantity' => 0,
                    'note' => $sourceBatch->note,
                ]
            );
            $destBatch->increment('quantity', $this->transfer_qty);

            $ref = 'Transfer #' . now()->format('YmdHis');

            StockMovement::create([
                'batch_id' => $sourceBatch->id,
                'quantity' => -$this->transfer_qty,
                'type' => 'transfer_out',
                'reference' => $ref,
                'note' => $this->transfer_note,
                'from_location_id' => $this->from_location_id,
                'to_location_id' => $this->to_location_id,
                'user_id' => auth()->id(),
            ]);

            StockMovement::create([
                'batch_id' => $destBatch->id,
                'quantity' => $this->transfer_qty,
                'type' => 'transfer_in',
                'reference' => $ref,
                'note' => $this->transfer_note,
                'from_location_id' => $this->from_location_id,
                'to_location_id' => $this->to_location_id,
                'user_id' => auth()->id(),
            ]);
        });

        $this->modal = false;
        $this->success('Stock transferred successfully.');
        $this->reset(['product_id', 'batch_id', 'from_location_id', 'to_location_id', 'transfer_qty', 'transfer_note']);
    }

    public function render()
    {
        $products = Product::orderBy('name')->get();
        $locations = Location::orderBy('name')->get();

        $batches = $this->product_id
            ? Batch::where('product_id', $this->product_id)
                ->where('quantity', '>', 0)
                ->with('location')
                ->get()
                ->map(fn($b) => [
                    'id' => $b->id,
                    'name' => $b->batch_number . ' — ' . ($b->location?->name ?? 'No location') . ' (' . $b->quantity . ' units)',
                ])
            : collect();

        $headers = [
            ['key' => 'created_at', 'label' => 'Date'],
            ['key' => 'batch.product.name', 'label' => 'Product'],
            ['key' => 'batch.batch_number', 'label' => 'Batch'],
            ['key' => 'quantity', 'label' => 'Qty'],
            ['key' => 'from', 'label' => 'From'],
            ['key' => 'to', 'label' => 'To'],
            ['key' => 'user.name', 'label' => 'By'],
        ];

        $transfers = StockMovement::with('batch.product', 'fromLocation', 'toLocation', 'user')
            ->where('type', 'transfer_out')
            ->latest()
            ->paginate(20);

        return view('livewire.stock.transfers', [
            'products' => $products,
            'locations' => $locations,
            'batches' => $batches,
            'headers' => $headers,
            'transfers' => $transfers,
        ]);
    }
}
