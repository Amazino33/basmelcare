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
 * Potential revenue is a snapshot of the shelf.
 *
 * What everything currently in stock would take if it all sold - not a figure
 * for the period selected on the dashboard, and not affected by it. Nothing
 * that cannot be sold belongs in it.
 */
class PotentialRevenueTest extends TestCase
{
    use RefreshDatabase;

    private function stock(float $price, float $cost, int $qty, string $expiry = '+1 year'): Batch
    {
        $product = Product::create([
            'name'          => 'PRODUCT ' . random_int(1000, 9999),
            'category_id'   => Category::firstOrCreate(['name' => 'MEDICINE'])->id,
            'selling_price' => $price,
            'reorder_level' => 1,
        ]);

        return Batch::create([
            'product_id'   => $product->id,
            'batch_number' => 'B' . random_int(1000, 9999),
            'expiry_date'  => now()->modify($expiry),
            'cost_price'   => $cost,
            'quantity'     => $qty,
        ]);
    }

    private function dashboard(?User $as = null)
    {
        return Livewire::actingAs($as ?? User::factory()->create(['role' => ['admin'], 'status' => 'active']))
            ->test(\App\Livewire\Dashboard::class);
    }

    public function test_it_is_what_the_shelf_would_take(): void
    {
        $this->stock(price: 1200, cost: 700, qty: 10);   // 12,000 / 7,000
        $this->stock(price: 500,  cost: 300, qty: 4);    //  2,000 / 1,200

        $page = $this->dashboard();

        $this->assertEquals(14000, $page->viewData('potentialRevenue'));
        $this->assertEquals(8200,  $page->viewData('potentialCost'));
        $this->assertEquals(5800,  $page->viewData('potentialProfit'));
    }

    public function test_an_empty_batch_counts_for_nothing(): void
    {
        $this->stock(price: 1200, cost: 700, qty: 0);

        $this->assertEquals(0, $this->dashboard()->viewData('potentialRevenue'));
    }

    public function test_expired_stock_is_left_out_of_both_sides(): void
    {
        // It cannot be sold. Counting it as revenue would flatter the figure,
        // and counting its cost would understate the profit on what can.
        $this->stock(price: 1200, cost: 700, qty: 10, expiry: '-1 day');

        $page = $this->dashboard();

        $this->assertEquals(0, $page->viewData('potentialRevenue'));
        $this->assertEquals(0, $page->viewData('potentialCost'));
    }

    public function test_stock_expiring_today_is_treated_as_gone(): void
    {
        $this->stock(price: 1200, cost: 700, qty: 10, expiry: 'today');

        $this->assertEquals(0, $this->dashboard()->viewData('potentialRevenue'));
    }

    public function test_the_date_filter_does_not_touch_it(): void
    {
        // The shelf has no date range. Every other figure on the dashboard is
        // for the chosen period; this one is what is there now.
        $this->stock(price: 1200, cost: 700, qty: 10);

        $today = $this->dashboard()->set('dateFilter', 'today')->viewData('potentialRevenue');
        $year  = $this->dashboard()->set('dateFilter', 'yesterday')->viewData('potentialRevenue');

        $this->assertEquals(12000, $today);
        $this->assertEquals(12000, $year);
    }

    // ── the silent understatement ───────────────────────────────────────

    public function test_unpriced_stock_is_reported_rather_than_silently_missing(): void
    {
        // Inventory staff can create a product without a price - it saves at
        // zero for a manager to set - so unpriced stock is a normal state. It
        // adds nothing to the figure, which has to be said or the number simply
        // looks too low.
        $this->stock(price: 1200, cost: 700, qty: 10);
        $this->stock(price: 0,    cost: 400, qty: 25);

        $page = $this->dashboard();

        $this->assertEquals(12000, $page->viewData('potentialRevenue'));
        $this->assertSame(25, $page->viewData('unpricedUnits'));
        $this->assertSame(1,  $page->viewData('unpricedProducts'));

        $page->assertSee('have no selling price');
    }

    public function test_nothing_is_said_when_everything_is_priced(): void
    {
        $this->stock(price: 1200, cost: 700, qty: 10);

        $page = $this->dashboard();

        $this->assertSame(0, $page->viewData('unpricedUnits'));
        $page->assertDontSee('have no selling price');
    }

    public function test_expired_unpriced_stock_is_not_counted_either(): void
    {
        // It would be pointless to price it, so nagging about it would be noise.
        $this->stock(price: 0, cost: 400, qty: 25, expiry: '-1 day');

        $this->assertSame(0, $this->dashboard()->viewData('unpricedUnits'));
    }

    public function test_an_empty_pharmacy_reports_zero_rather_than_breaking(): void
    {
        $page = $this->dashboard();

        $this->assertEquals(0, $page->viewData('potentialRevenue'));
        $this->assertEquals(0, $page->viewData('potentialProfit'));
    }
}
