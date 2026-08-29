<?php

namespace App\Livewire\Sales;

use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use Carbon\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Everything that came back.
 *
 * Its own page rather than only a tab inside Sales History, because that page
 * carries revenue and profit and so is closed to most of the pharmacy. What
 * came back off the shelf is not a margin figure - the pharmacist wants to know
 * a drug was returned, the inventory manager wants to know it is back in stock,
 * and the auditor has to be able to check both. So this is open to everyone and
 * shows no margin.
 *
 * Read-only for all of them. Processing a return stays with a manager, on the
 * sale it belongs to.
 */
class Returns extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    /** all | cash | credit */
    #[Url]
    public string $methodFilter = 'all';

    #[Url]
    public string $period = 'month';

    public ?int $viewId = null;
    public bool $detailDrawer = false;

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedMethodFilter(): void { $this->resetPage(); }
    public function updatedPeriod(): void { $this->resetPage(); }

    public function viewReturn(int $id): void
    {
        $this->viewId       = $id;
        $this->detailDrawer = true;
    }

    public function closeDetail(): void
    {
        $this->detailDrawer = false;
        $this->viewId       = null;
    }

    private function range(): array
    {
        return match ($this->period) {
            'today' => [today()->startOfDay(), today()->endOfDay()],
            'week'  => [now()->startOfWeek(), now()->endOfWeek()],
            'year'  => [now()->startOfYear(), now()->endOfYear()],
            'all'   => [Carbon::create(2000), now()->endOfDay()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    public function render()
    {
        [$from, $to] = $this->range();

        $base = SaleReturn::with(['sale.customer', 'processor', 'items.product'])
            ->whereBetween('created_at', [$from, $to])
            ->when($this->methodFilter !== 'all', fn ($q) => $q->where('refund_method', $this->methodFilter))
            ->when($this->search, fn ($q) => $q
                ->where('id', $this->search)
                ->orWhereHas('sale', fn ($s) => $s->where('invoice_number', 'like', "%{$this->search}%"))
                ->orWhereHas('sale.customer', fn ($c) => $c->where('name', 'like', "%{$this->search}%"))
                ->orWhereHas('items.product', fn ($p) => $p->where('name', 'like', "%{$this->search}%")));

        $total = (float) (clone $base)->sum('total_credit');
        $cash  = (float) (clone $base)->where('refund_method', SaleReturn::CASH)->sum('total_credit');

        return view('livewire.sales.returns', [
            'returns'  => (clone $base)->latest('id')->paginate(20),
            'count'    => (clone $base)->count(),
            'total'    => $total,
            'cash'     => $cash,
            'credit'   => round($total - $cash, 2),
            'units'    => (int) SaleReturnItem::whereIn(
                'sale_return_id', (clone $base)->select('sale_returns.id')
            )->sum('quantity_returned'),
            'viewReturn' => $this->viewId
                ? SaleReturn::with(['sale.customer', 'processor', 'items.product', 'items.batch'])->find($this->viewId)
                : null,
        ]);
    }
}
