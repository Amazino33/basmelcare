<?php

namespace App\Livewire\Stock;

use App\Models\FailedSearch;
use App\Models\Product;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * What the pharmacy cannot currently sell, in one buying list.
 *
 * Two kinds, kept apart because they need different action:
 *
 *   asked for  - a customer wanted it and it is not in the catalogue at all.
 *                Sourcing one means deciding to stock it for the first time.
 *   out of stock - in the catalogue with nothing on the shelf. Sourcing one
 *                means reordering something already sold before.
 *
 * "Got it" only clears the entry and records who and when. It does not create
 * the product or add the stock: those are deliberate acts with their own
 * screens, and a button that half-did them would leave the catalogue in a
 * state nobody chose.
 *
 * The mark expires on its own. A product's clears when stock actually arrives,
 * and a search's clears if somebody asks again and still finds nothing - so a
 * shortage that returns is never hidden by an old tick.
 */
class Unavailable extends Component
{
    use Toast;

    /** all | asked | stock */
    #[Url]
    public string $filter = 'all';

    public string $search = '';

    private function canSource(): bool
    {
        return (bool) array_intersect(
            auth()->user()->role ?? [],
            ['admin', 'branch_manager', 'inventory_manager'],
        );
    }

    public function markSearchSourced(int $id): void
    {
        if (! $this->canSource()) {
            $this->error('You cannot mark items as sourced.');

            return;
        }

        $search = FailedSearch::withoutGlobalScopes()->findOrFail($id);

        $search->forceFill([
            'sourced_at' => now(),
            'sourced_by' => auth()->id(),
        ])->save();

        $this->success($search->term . ' marked as sourced.');
    }

    public function markProductSourced(int $id): void
    {
        if (! $this->canSource()) {
            $this->error('You cannot mark items as sourced.');

            return;
        }

        $product = Product::findOrFail($id);

        $product->forceFill([
            'sourced_at' => now(),
            'sourced_by' => auth()->id(),
        ])->save();

        $this->success($product->name . ' marked as sourced. It leaves the list until stock arrives.');
    }

    public function undoSearch(int $id): void
    {
        if (! $this->canSource()) {
            return;
        }

        FailedSearch::withoutGlobalScopes()->findOrFail($id)
            ->forceFill(['sourced_at' => null, 'sourced_by' => null])->save();

        $this->success('Put back on the list.');
    }

    public function undoProduct(int $id): void
    {
        if (! $this->canSource()) {
            return;
        }

        Product::findOrFail($id)
            ->forceFill(['sourced_at' => null, 'sourced_by' => null])->save();

        $this->success('Put back on the list.');
    }

    public function render()
    {
        $term = trim($this->search);

        $asked = FailedSearch::outstanding()
            ->with('lastUser')
            ->when($term !== '', fn ($q) => $q->where('term', 'like', '%' . $term . '%'))
            // Most asked for first: the strongest signal of what to buy.
            ->orderByDesc('times')
            ->orderByDesc('last_searched_at')
            ->limit(100)
            ->get();

        $outOfStock = Product::unsellable()
            ->with('category')
            ->when($term !== '', fn ($q) => $q->where('name', 'like', '%' . $term . '%'))
            ->orderBy('name')
            ->limit(200)
            ->get();

        return view('livewire.stock.unavailable', [
            'asked'      => $this->filter === 'stock' ? collect() : $asked,
            'outOfStock' => $this->filter === 'asked' ? collect() : $outOfStock,
            'askedCount' => FailedSearch::outstanding()->count(),
            'stockCount' => Product::unsellable()->count(),
            'canSource'  => $this->canSource(),
            // Shown so a tick can be undone if it was a mistake.
            'sourced'    => $this->filter === 'all'
                ? FailedSearch::withoutGlobalScopes()->whereNotNull('sourced_at')
                    ->with('sourcedBy')->latest('sourced_at')->limit(10)->get()
                : collect(),
        ]);
    }
}
