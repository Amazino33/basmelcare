<?php

namespace App\Livewire\Dashboard;

use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * What the shelf is worth right now, kept up to date as it empties.
 *
 * Its own component so it can refresh on its own. The dashboard around it
 * fires some thirty-five queries to build a picture of a chosen period;
 * polling all of that every few seconds to move one card would be a heavy way
 * to get a cheap answer. This is two aggregates over batches, so it can look
 * again often without costing anything.
 *
 * A snapshot, deliberately: no date range applies. Everything else on the
 * dashboard answers "what happened during this period"; this one answers "what
 * is in the building", which has no period.
 */
class PotentialProfit extends Component
{
    /** Who may see it. Margin is not everyone's to read. */
    public function canSee(): bool
    {
        return (bool) array_intersect(auth()->user()->role ?? [], ['admin', 'pharmacist', 'branch_manager']);
    }

    /**
     * The shelf, priced to sell and priced at cost.
     *
     * Expired stock is left out of both sides. It cannot be sold, so counting
     * it as revenue would flatter the figure, and counting its cost would
     * understate the profit on what can actually be sold.
     */
    public function figures(): array
    {
        $sellable = fn () => DB::table('batches')
            ->join('products', 'products.id', '=', 'batches.product_id')
            ->where('batches.quantity', '>', 0)
            ->whereDate('batches.expiry_date', '>', today());

        $onShelf = $sellable()
            ->selectRaw('COALESCE(SUM(products.selling_price * batches.quantity), 0) AS revenue')
            ->selectRaw('COALESCE(SUM(batches.cost_price * batches.quantity), 0) AS cost')
            ->first();

        $revenue = (float) ($onShelf->revenue ?? 0);
        $cost    = (float) ($onShelf->cost ?? 0);

        // Stock that has never been priced counts as nothing towards revenue,
        // which quietly makes the figure too low. Inventory staff can create a
        // product without a price - it saves at zero for a manager to set - so
        // this is a normal state rather than a fault, and it has to be said or
        // the number simply looks wrong.
        $unpriced = $sellable()
            ->where('products.selling_price', '<=', 0)
            ->selectRaw('COUNT(DISTINCT products.id) AS products')
            ->selectRaw('COALESCE(SUM(batches.quantity), 0) AS units')
            ->first();

        return [
            'potentialRevenue'  => $revenue,
            'potentialCost'     => $cost,
            'potentialProfit'   => $revenue - $cost,
            'unpricedProducts'  => (int) ($unpriced->products ?? 0),
            'unpricedUnits'     => (int) ($unpriced->units ?? 0),
        ];
    }

    public function render()
    {
        if (! $this->canSee()) {
            return view('livewire.dashboard.potential-profit-hidden');
        }

        return view('livewire.dashboard.potential-profit', $this->figures());
    }
}
