<?php

namespace App\Livewire\Shop;

use App\Models\Category;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * The shop front.
 *
 * A Livewire component rather than a plain view, because every product on it
 * carries an add-to-basket button and those have to do something.
 *
 * Every rail is driven by real data. "Top selling" is what has actually sold
 * most, counted from the sale items; the category rails are the categories
 * that actually have stock. A hand-written list of featured products would go
 * stale the first week and nobody would notice.
 */
#[Layout('layouts.public')]
class Home extends Component
{
    use Toast;

    public function addToCart(int $productId): void
    {
        $product = Product::where('show_in_shop', true)->find($productId);

        if (! $product) {
            return;
        }

        // Nothing that is not on the shelf. The button is not rendered for an
        // empty product, but the action is what decides.
        if ($product->batches()->sum('quantity') < 1) {
            $this->warning('That one is out of stock. Ask at the counter and we will source it.');

            return;
        }

        (new CartService)->add($productId);

        $this->success(\Illuminate\Support\Str::title(\Illuminate\Support\Str::lower($product->name)) . ' added to your basket.');
    }

    /** Products on sale, with what the card needs, in one query each. */
    private function sellable()
    {
        return Product::with(['category', 'batches'])
            ->where('show_in_shop', true);
    }

    /**
     * What has actually moved, most first.
     *
     * Counted in units off the sale items rather than by number of orders, so
     * a box of thirty does not rank below a single tube bought three times.
     */
    private function bestSellers(int $limit = 8)
    {
        $ranked = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereIn('sales.status', ['paid', 'completed'])
            ->groupBy('sale_items.product_id')
            ->orderByDesc(DB::raw('SUM(sale_items.quantity)'))
            ->limit($limit * 3)
            ->pluck('sale_items.product_id');

        if ($ranked->isEmpty()) {
            return collect();
        }

        // Ordered by how well they sold, not by id - whereIn does not preserve
        // the ranking, so it is reapplied here.
        return $this->sellable()
            ->whereIn('id', $ranked)
            ->get()
            ->sortBy(fn ($product) => $ranked->search($product->id))
            ->take($limit)
            ->values();
    }

    public function render()
    {
        $bestSellers = $this->bestSellers();

        // A new pharmacy has sold nothing yet. Rather than an empty rail, the
        // newest stock stands in - which is what a customer wants to see then
        // anyway.
        if ($bestSellers->isEmpty()) {
            $bestSellers = $this->sellable()->latest()->limit(8)->get();
        }

        // whereHas rather than having: withCount builds a subquery with no
        // GROUP BY, and SQLite refuses a HAVING on one. The tests run on
        // SQLite and production is MySQL, so the query has to suit both.
        $categories = Category::query()
            ->withCount(['products' => fn ($q) => $q->where('show_in_shop', true)])
            ->whereHas('products', fn ($q) => $q->where('show_in_shop', true))
            ->orderByDesc('products_count')
            ->get();

        // Two category rails, from the categories that actually have the most
        // to show. Named after the shelf rather than invented.
        $rails = $categories->take(2)->map(fn ($category) => [
            'category' => $category,
            'products' => $this->sellable()
                ->where('category_id', $category->id)
                ->latest()
                ->limit(8)
                ->get(),
        ])->filter(fn ($rail) => $rail['products']->isNotEmpty());

        return view('livewire.shop.home', [
            'bestSellers'   => $bestSellers,
            'newArrivals'   => $this->sellable()->latest()->limit(8)->get(),
            'categories'    => $categories->take(6),
            'rails'         => $rails,
            'categoryCount' => $categories->count(),
        ]);
    }
}
