<?php

namespace App\Livewire\Stock;

use App\Models\StockMovement;
use Carbon\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * Everything taken into stock, grouped as it actually happened.
 *
 * The auditor asked to see a particular delivery - some products new to the
 * catalogue, some already stocked. Stock movements record each line, but a
 * delivery is not one row: it is however many rows one person entered in one
 * sitting. So they are grouped by the day and the person, which is the
 * closest thing to a delivery the system honestly knows.
 *
 * Read-only by design. The auditor is on the list of who may open it, and the
 * auditor may not move stock.
 */
class Received extends Component
{
    use Toast;

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
     * deliveries and would drown what the auditor is looking for.
     */
    private function movements()
    {
        return StockMovement::query()
            ->with(['batch.product.category', 'user', 'toLocation'])
            ->where('type', 'purchase')
            ->whereBetween('created_at', [
                Carbon::parse($this->dateFrom)->startOfDay(),
                Carbon::parse($this->dateTo)->endOfDay(),
            ])
            ->when($this->search, fn ($q) => $q->whereHas(
                'batch.product',
                fn ($p) => $p->where('name', 'like', '%' . $this->search . '%')
            ))
            ->orderByDesc('created_at')
            ->get();
    }

    public function render()
    {
        $movements = $this->movements();

        // Grouped by day and by who entered it. Anything without a user is
        // from before that was recorded - shown as unattributed rather than
        // hidden, because the gap is itself something the auditor should see.
        $intakes = $movements
            ->groupBy(fn ($m) => $m->created_at->format('Y-m-d') . '|' . ($m->user_id ?: 'unknown'))
            ->map(function ($lines) {
                $first = $lines->first();

                return [
                    'date'      => $first->created_at,
                    'by'        => $first->user?->name,
                    'lines'     => $lines,
                    'units'     => $lines->sum('quantity'),
                    'value'     => $lines->sum(fn ($m) => $m->quantity * (float) ($m->batch->cost_price ?? 0)),
                    // "New" means this batch was the product's first, which is
                    // what distinguishes a product added to the catalogue from
                    // a top-up of one already stocked.
                    'newCount'  => $lines->filter(fn ($m) => $m->reference === 'Opening stock')->count(),
                ];
            })
            ->sortByDesc(fn ($intake) => $intake['date'])
            ->values();

        return view('livewire.stock.received', [
            'intakes'        => $intakes,
            'totalUnits'     => $movements->sum('quantity'),
            'totalValue'     => $movements->sum(fn ($m) => $m->quantity * (float) ($m->batch->cost_price ?? 0)),
            'unattributed'   => $movements->whereNull('user_id')->count(),
        ]);
    }
}
