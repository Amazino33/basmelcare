<?php

namespace App\Livewire\Stock;

use App\Models\StockMovement;
use Carbon\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * Everything taken into stock, read two ways.
 *
 * "Deliveries" groups intake by the day and the person, because a delivery is
 * not one row: it is however many lines one person entered in one sitting,
 * and that is the closest thing to a delivery the data honestly supports.
 *
 * "Opening stock" answers a different question - what the pharmacy started
 * with. One line per product, the first time it was ever stocked, at the
 * quantity and cost it came in at.
 *
 * That figure is exact rather than reconstructed. batches.quantity is what is
 * left after sales, but the stock movement that created the batch still
 * carries the number that went in.
 *
 * Read-only. The auditor may open it and may not move stock.
 */
class Received extends Component
{
    use Toast;

    /** deliveries | opening */
    #[Url]
    public string $view = 'deliveries';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    public string $search = '';

    public function mount(): void
    {
        $this->dateFrom = $this->dateFrom ?: now()->startOfMonth()->format('Y-m-d');
        $this->dateTo   = $this->dateTo ?: now()->format('Y-m-d');
    }

    /**
     * Intake only. Sales and returns move stock too, but they are not
     * deliveries and would drown what is being looked for.
     */
    private function movements(bool $withinDates = true)
    {
        return StockMovement::query()
            ->with(['batch.product.category', 'user'])
            ->where('type', 'purchase')
            ->when($withinDates, fn ($q) => $q->whereBetween('created_at', [
                Carbon::parse($this->dateFrom)->startOfDay(),
                Carbon::parse($this->dateTo)->endOfDay(),
            ]))
            ->when($this->search, fn ($q) => $q->whereHas(
                'batch.product',
                fn ($p) => $p->where('name', 'like', '%' . $this->search . '%')
            ))
            ->orderBy('created_at')
            ->get();
    }

    /**
     * The first time each product was ever stocked.
     *
     * Deliberately not limited by the date filter: the question is what the
     * pharmacy started with, and answering it requires reaching back past
     * whatever range someone happens to be looking at.
     */
    private function openingStock()
    {
        return $this->movements(withinDates: false)
            ->filter(fn ($m) => $m->batch?->product)
            ->groupBy(fn ($m) => $m->batch->product_id)
            // Movements come back oldest first, so the first of each group is
            // the first time that product was stocked.
            ->map(fn ($lines) => $lines->first())
            ->sortBy([
                fn ($m) => $m->created_at->timestamp,
                fn ($m) => $m->batch->product->name,
            ])
            ->values();
    }

    public function render()
    {
        if ($this->view === 'opening') {
            $opening = $this->openingStock();

            return view('livewire.stock.received', [
                'view'         => 'opening',
                'opening'      => $opening->groupBy(fn ($m) => $m->created_at->format('Y-m-d')),
                'openingCount' => $opening->count(),
                'openingUnits' => $opening->sum('quantity'),
                'openingValue' => $opening->sum(fn ($m) => $m->quantity * (float) ($m->batch->cost_price ?? 0)),
                'intakes'      => collect(),
                'totalUnits'   => 0,
                'totalValue'   => 0,
                'unattributed' => 0,
            ]);
        }

        $movements = $this->movements();

        // Grouped by day and by who entered it. Anything without a user is
        // from before that was recorded - shown as unattributed rather than
        // hidden, because the gap is itself something the auditor should see.
        $intakes = $movements
            ->groupBy(fn ($m) => $m->created_at->format('Y-m-d') . '|' . ($m->user_id ?: 'unknown'))
            ->map(function ($lines) {
                $first = $lines->first();

                return [
                    'date'     => $first->created_at,
                    'by'       => $first->user?->name,
                    'lines'    => $lines,
                    'units'    => $lines->sum('quantity'),
                    'value'    => $lines->sum(fn ($m) => $m->quantity * (float) ($m->batch->cost_price ?? 0)),
                    'newCount' => $lines->filter(fn ($m) => $m->reference === 'Opening stock')->count(),
                ];
            })
            ->sortByDesc(fn ($intake) => $intake['date'])
            ->values();

        return view('livewire.stock.received', [
            'view'         => 'deliveries',
            'intakes'      => $intakes,
            'totalUnits'   => $movements->sum('quantity'),
            'totalValue'   => $movements->sum(fn ($m) => $m->quantity * (float) ($m->batch->cost_price ?? 0)),
            'unattributed' => $movements->whereNull('user_id')->count(),
            'opening'      => collect(),
            'openingCount' => 0,
            'openingUnits' => 0,
            'openingValue' => 0,
        ]);
    }
}
