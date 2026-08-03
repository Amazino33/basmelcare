<?php

namespace App\Livewire\StockTake;

use App\Models\StockMovement;
use App\Models\StockTake;
use App\Models\StockTakeItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Show extends Component
{
    use Toast, WithPagination;

    public StockTake $stockTake;
    public array $physicalQtys = [];
    public string $search = '';
    public string $filter = 'all'; // all | pending | discrepancy
    public string $approvalNotes = '';
    public bool $rejectModal = false;

    public function mount(StockTake $stockTake): void
    {
        $this->stockTake = $stockTake;

        $this->physicalQtys = $stockTake->items()
            ->pluck('physical_qty', 'product_id')
            ->map(fn($v) => $v !== null ? (string) $v : '')
            ->toArray();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPhysicalQtys(string $productId): void
    {
        if ($this->stockTake->status !== 'in_progress') return;

        $value = $this->physicalQtys[$productId] ?? '';

        StockTakeItem::where('stock_take_id', $this->stockTake->id)
            ->where('product_id', (int) $productId)
            ->update(['physical_qty' => $value !== '' ? max(0, (int) $value) : null]);
    }

    public function submit(): void
    {
        if ($this->stockTake->status !== 'in_progress') return;

        $uncounted = StockTakeItem::where('stock_take_id', $this->stockTake->id)
            ->whereNull('physical_qty')
            ->count();

        if ($uncounted > 0) {
            $this->error("{$uncounted} product(s) have not been counted yet. Enter 0 for products with no stock.");
            return;
        }

        $this->stockTake->update([
            'status'       => 'pending_approval',
            'submitted_at' => now(),
        ]);

        $this->stockTake->refresh();
        $this->success('Stock take submitted for approval.');
    }

    public function approve(): void
    {
        if (!$this->isApprover()) {
            $this->error('You do not have permission to approve stock takes.');
            return;
        }

        if ($this->stockTake->status !== 'pending_approval') {
            $this->error('This stock take is not pending approval.');
            return;
        }

        $stockTakeId = $this->stockTake->getKey();
        $userId      = Auth::id();

        try {
            DB::transaction(function () use ($stockTakeId, $userId) {
                foreach ($this->stockTake->items()->with('product.batches')->get() as $item) {
                    $diff = $item->physical_qty - $item->system_qty;
                    if ($diff === 0) continue;

                    $batches = $item->product->batches;
                    if ($batches->isEmpty()) continue;

                    if ($diff > 0) {
                        $batch = $batches->sortByDesc('expiry_date')->first();
                        $batch->increment('quantity', $diff);
                        StockMovement::create([
                            'batch_id'  => $batch->getKey(),
                            'quantity'  => $diff,
                            'type'      => 'adjustment',
                            'reference' => "Stock take #{$stockTakeId} — surplus",
                            'user_id'   => $userId,
                        ]);
                    } else {
                        $remaining = abs($diff);
                        foreach ($batches->sortBy('expiry_date') as $batch) {
                            if ($remaining <= 0) break;
                            $deduct = min($remaining, $batch->quantity);
                            if ($deduct > 0) {
                                $batch->decrement('quantity', $deduct);
                                StockMovement::create([
                                    'batch_id'  => $batch->getKey(),
                                    'quantity'  => $deduct,
                                    'type'      => 'adjustment',
                                    'reference' => "Stock take #{$stockTakeId} — shortage",
                                    'user_id'   => $userId,
                                ]);
                            }
                            $remaining -= $deduct;
                        }
                    }
                }

                $this->stockTake->update([
                    'status'      => 'approved',
                    'approved_by' => $userId,
                    'notes'       => $this->approvalNotes ?: null,
                    'approved_at' => now(),
                ]);
            });

            $this->stockTake->refresh();
            $this->success('Stock take approved and inventory adjusted.');
        } catch (\Throwable) {
            $this->error('Failed to apply adjustments. Please try again.');
        }
    }

    public function reject(): void
    {
        if (!$this->isApprover()) {
            $this->error('You do not have permission to reject stock takes.');
            return;
        }

        if ($this->stockTake->status !== 'pending_approval') {
            $this->error('This stock take is not pending approval.');
            return;
        }

        $this->stockTake->update([
            'status'      => 'rejected',
            'approved_by' => Auth::id(),
            'notes'       => $this->approvalNotes ?: null,
            'approved_at' => now(),
        ]);

        $this->rejectModal    = false;
        $this->stockTake->refresh();
        $this->success('Stock take rejected.');
    }

    private function isApprover(): bool
    {
        return (bool) array_intersect(Auth::user()?->role ?? [], ['admin', 'branch_manager']);
    }

    public function render()
    {
        $items = $this->stockTake->items()
            ->with('product.category')
            ->when($this->search, fn($q) => $q->whereHas('product',
                fn($p) => $p->where('name', 'like', "%{$this->search}%")
            ))
            ->when($this->filter === 'pending',      fn($q) => $q->whereNull('physical_qty'))
            ->when($this->filter === 'discrepancy',  fn($q) => $q->whereNotNull('physical_qty')->whereColumn('physical_qty', '!=', 'system_qty'))
            ->join('products', 'products.id', '=', 'stock_take_items.product_id')
            ->orderByRaw($this->filter === 'pending' ? '1=1' : 'products.name')
            ->orderBy('products.name')
            ->select('stock_take_items.*')
            ->paginate(50);

        $totalProducts   = $this->stockTake->items()->count();
        $countedProducts = $this->stockTake->items()->whereNotNull('physical_qty')->count();
        $discrepancies   = $this->stockTake->items()
            ->whereNotNull('physical_qty')
            ->whereColumn('physical_qty', '!=', 'system_qty')
            ->count();

        return view('livewire.stock-take.show', [
            'items'           => $items,
            'totalProducts'   => $totalProducts,
            'countedProducts' => $countedProducts,
            'discrepancies'   => $discrepancies,
            'isApprover'      => $this->isApprover(),
        ]);
    }
}
