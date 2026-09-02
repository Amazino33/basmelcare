<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * What the dashboard says about stock, and what happens when you click it.
 *
 * Both tiles link straight to the Products page with a filter. The number and
 * the list it opens have to be the same set - a count that does not match the
 * page it leads to is worse than no count, because it is believed.
 */
class DashboardStockFiguresTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => ['admin'], 'status' => 'active']);
    }

    /** A product with the given total on the shelf, or none at all. */
    private function product(string $name, ?int $stock, int $reorderLevel = 5): Product
    {
        $product = Product::create([
            'name'          => $name,
            'category_id'   => Category::firstOrCreate(['name' => 'MEDICINE'])->id,
            'selling_price' => 100,
            'reorder_level' => $reorderLevel,
        ]);

        if ($stock !== null) {
            Batch::create([
                'product_id'   => $product->id,
                'batch_number' => 'B' . random_int(1000, 9999),
                'expiry_date'  => now()->addYear(),
                'cost_price'   => 60,
                'quantity'     => $stock,
            ]);
        }

        return $product->fresh();
    }

    private function dashboard()
    {
        return Livewire::actingAs($this->admin())->test(\App\Livewire\Dashboard::class);
    }

    private function productsPage(string $filter)
    {
        return Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Products\Index::class)
            ->set('stockFilter', $filter);
    }

    // ── the figures ─────────────────────────────────────────────────────

    public function test_it_counts_what_has_run_out(): void
    {
        $this->product('EMPTY', 0);
        $this->product('PLENTY', 100);

        $this->assertSame(1, $this->dashboard()->viewData('outOfStock'));
    }

    public function test_it_counts_what_is_at_or_below_the_reorder_level(): void
    {
        $this->product('AT THE LINE', 5, reorderLevel: 5);
        $this->product('BELOW IT', 2, reorderLevel: 5);
        $this->product('ABOVE IT', 50, reorderLevel: 5);

        $this->assertSame(2, $this->dashboard()->viewData('lowStockCount'));
    }

    public function test_something_that_has_run_out_is_not_also_counted_as_low(): void
    {
        // It needs ordering, not watching, and counting it twice overstates
        // both figures.
        $this->product('EMPTY', 0, reorderLevel: 5);

        $this->assertSame(1, $this->dashboard()->viewData('outOfStock'));
        $this->assertSame(0, $this->dashboard()->viewData('lowStockCount'));
    }

    public function test_a_product_with_no_reorder_level_never_reads_as_low(): void
    {
        // Nothing to be below. Worth knowing, because a catalogue where nobody
        // set reorder levels shows an empty Low Stock and looks broken.
        $this->product('NO THRESHOLD', 1, reorderLevel: 0);

        $this->assertSame(0, $this->dashboard()->viewData('lowStockCount'));
    }

    public function test_a_product_never_stocked_is_reported_apart(): void
    {
        // It counts as out of stock, but it is a different job: somebody set up
        // a product and never received any.
        $this->product('NEVER RECEIVED', null);
        $this->product('RAN OUT', 0);

        $page = $this->dashboard();

        $this->assertSame(2, $page->viewData('outOfStock'));
        $this->assertSame(1, $page->viewData('neverStocked'));
    }

    // ── the number and the page it opens must agree ─────────────────────

    public function test_the_out_of_stock_tile_matches_the_page_it_links_to(): void
    {
        $this->product('EMPTY', 0);
        $this->product('NEVER RECEIVED', null);
        $this->product('LOW', 2, reorderLevel: 5);
        $this->product('PLENTY', 100);

        $this->assertSame(
            $this->dashboard()->viewData('outOfStock'),
            $this->productsPage('out_of_stock')->viewData('products')->total(),
            'The tile and the list it opens disagree.'
        );
    }

    public function test_the_low_stock_tile_matches_the_page_it_links_to(): void
    {
        $this->product('EMPTY', 0);
        $this->product('AT THE LINE', 5, reorderLevel: 5);
        $this->product('BELOW IT', 2, reorderLevel: 5);
        $this->product('NO THRESHOLD', 3, reorderLevel: 0);
        $this->product('PLENTY', 100);

        $this->assertSame(
            $this->dashboard()->viewData('lowStockCount'),
            $this->productsPage('low_stock')->viewData('products')->total(),
            'The tile and the list it opens disagree.'
        );
    }

    // ── the list beneath it ─────────────────────────────────────────────

    public function test_the_list_shows_the_shortest_first(): void
    {
        $this->product('NEARLY GONE', 1, reorderLevel: 10);
        $this->product('GETTING THERE', 8, reorderLevel: 10);

        $names = $this->dashboard()->viewData('lowStockProducts')->pluck('name')->all();

        $this->assertSame(['NEARLY GONE', 'GETTING THERE'], $names);
    }

    public function test_an_empty_catalogue_reports_zero_rather_than_breaking(): void
    {
        $page = $this->dashboard();

        $this->assertSame(0, $page->viewData('outOfStock'));
        $this->assertSame(0, $page->viewData('lowStockCount'));
    }

    public function test_both_figures_are_on_the_page(): void
    {
        // Low stock existed only as a list at the very bottom, so the figure
        // worth acting on was the one nobody saw.
        $this->product('LOW', 2, reorderLevel: 5);

        $this->dashboard()
            ->assertSee('Out of Stock')
            ->assertSee('Low Stock');
    }
}
