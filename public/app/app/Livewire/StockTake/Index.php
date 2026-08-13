<?php

namespace App\Livewire\StockTake;

use App\Models\Product;
use App\Models\StockTake;
use App\Models\StockTakeItem;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast, WithPagination;

    public function startStockTake(): void
    {
        $active = StockTake::whereIn('status', ['in_progress', 'pending_approval'])->exists();
        if ($active) {
            $this->error('A stock take is already in progress or awaiting approval. Complete it before starting a new one.');
            return;
        }

        $stockTake = StockTake::create([
            'started_by' => auth()->id(),
            'status'     => 'in_progress',
        ]);

        $now   = now();
        $items = Product::with('batches')->get()->map(fn($p) => [
            'stock_take_id' => $stockTake->id,
            'product_id'    => $p->id,
            'system_qty'    => $p->batches->sum('quantity'),
            'physical_qty'  => null,
            'created_at'    => $now,
            'updated_at'    => $now,
        ])->toArray();

        StockTakeItem::insert($items);

        $this->redirect(route('stock-take.show', $stockTake), navigate: true);
    }

    public function render()
    {
        $stockTakes = StockTake::with('starter', 'approver')
            ->withCount('items')
            ->withCount(['items as discrepancies_count' => fn($q) => $q
                ->whereNotNull('physical_qty')
                ->whereColumn('physical_qty', '!=', 'system_qty')
            ])
            ->latest()
            ->paginate(20);

        $activeStockTake = StockTake::whereIn('status', ['in_progress', 'pending_approval'])
            ->latest()
            ->first();

        return view('livewire.stock-take.index', compact('stockTakes', 'activeStockTake'));
    }
}
