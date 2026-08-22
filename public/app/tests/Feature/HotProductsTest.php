<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Category;
use App\Models\FailedSearch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Hot" is measured three ways because they disagree: the drug that moves the
 * most units can be the one that earns the least. Reporting one number would
 * hide that, so units, revenue and profit are each ranked.
 *
 * Failed searches capture the opposite - demand that produced no sale at all,
 * and therefore appears in no sales report.
 */
class HotProductsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => ['admin'], 'status' => 'active']);
    }

    /** Sells $qty of a product across $sales separate invoices. */
    private function sell(string $name, float $price, float $cost, int $qty, int $sales = 1): Product
    {
        $product = Product::firstOrCreate(
            ['name' => $name],
            [
                'category_id' => Category::firstOrCreate(['name' => 'General'])->id,
                'selling_price' => $price, 'reorder_level' => 1,
            ]
        );

        $perSale = intdiv($qty, $sales);

        for ($i = 0; $i < $sales; $i++) {
            $batch = Batch::create([
                'product_id' => $product->id, 'batch_number' => 'B-' . random_int(1000, 9999),
                'expiry_date' => now()->addYear(), 'cost_price' => $cost, 'quantity' => 500,
            ]);

            $sale = Sale::create([
                'invoice_number' => 'INV-' . random_int(100000, 999999),
                'user_id' => User::factory()->create(['role' => ['cashier']])->id,
                'total_amount' => $price * $perSale,
                'coupon_discount' => 0,
                'status' => 'completed',
            ]);

            SaleItem::create([
                'sale_id' => $sale->id, 'product_id' => $product->id, 'batch_id' => $batch->id,
                'quantity' => $perSale, 'unit_price' => $price,
                'cost_price' => $cost, 'subtotal' => $price * $perSale,
            ]);
        }

        return $product;
    }

    private function dashboard()
    {
        return Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Dashboard::class)
            ->set('dateFilter', 'custom')
            ->set('dateFrom', today()->subYear()->format('Y-m-d'))
            ->set('dateTo', today()->format('Y-m-d'));
    }

    public function test_the_three_measures_can_disagree(): void
    {
        // Cheap, high volume, thin margin.
        $this->sell('PARACETAMOL', price: 160, cost: 85, qty: 125, sales: 5);
        // Dearer, lower volume, fat margin.
        $this->sell('CIPROFLOXACIN', price: 1730, cost: 1100, qty: 45, sales: 9);

        $hot = $this->dashboard()->viewData('hot');

        $this->assertSame('PARACETAMOL', $hot['byUnits']->first()->name);
        $this->assertSame('CIPROFLOXACIN', $hot['byRevenue']->first()->name);
        $this->assertSame('CIPROFLOXACIN', $hot['byProfit']->first()->name,
            'The most profitable product should not be assumed to be the best seller.');
    }

    public function test_profit_is_revenue_minus_cost(): void
    {
        $this->sell('AMOXICILLIN', price: 1000, cost: 600, qty: 10, sales: 1);

        $top = $this->dashboard()->viewData('hot')['byProfit']->first();

        $this->assertEquals(10000, $top->revenue);
        $this->assertEquals(4000, $top->profit);
    }

    public function test_it_counts_how_many_separate_sales(): void
    {
        // 25 units in ONE sale is a bulk order, not a popular product.
        $this->sell('AMLODIPINE', price: 1200, cost: 700, qty: 25, sales: 1);
        // Same units spread over many sales is real demand.
        $this->sell('METFORMIN', price: 1200, cost: 700, qty: 25, sales: 5);

        $rows = $this->dashboard()->viewData('hot')['byUnits'];

        $bulk   = $rows->firstWhere('name', 'AMLODIPINE');
        $steady = $rows->firstWhere('name', 'METFORMIN');

        $this->assertSame(1, (int) $bulk->times_sold);
        $this->assertSame(5, (int) $steady->times_sold);
    }

    public function test_unsettled_sales_are_excluded(): void
    {
        $product = $this->sell('IBUPROFEN', price: 500, cost: 300, qty: 4, sales: 1);
        Sale::query()->update(['status' => 'pending']);

        $hot = $this->dashboard()->viewData('hot');

        $this->assertFalse($hot['any'], 'A pending invoice is not a sale.');
    }

    public function test_each_list_is_capped(): void
    {
        foreach (range(1, 8) as $i) {
            $this->sell("DRUG {$i}", price: 100 * $i, cost: 50, qty: $i, sales: 1);
        }

        $hot = $this->dashboard()->viewData('hot');

        $this->assertCount(5, $hot['byUnits']);
        $this->assertCount(5, $hot['byProfit']);
    }

    public function test_the_panel_renders_for_an_admin(): void
    {
        $this->sell('PARACETAMOL', price: 160, cost: 85, qty: 125, sales: 5);

        $html = $this->dashboard()->html();

        $this->assertStringContainsString('Most units sold', $html);
        $this->assertStringContainsString('Most revenue', $html);
        $this->assertStringContainsString('Most profit', $html);
    }

    public function test_a_cashier_does_not_see_the_panel(): void
    {
        $this->sell('PARACETAMOL', price: 160, cost: 85, qty: 10, sales: 1);

        $html = Livewire::actingAs(User::factory()->create([
            'role' => ['cashier'], 'status' => 'active',
        ]))->test(\App\Livewire\Dashboard::class)->html();

        $this->assertStringNotContainsString('Most profit', $html);
    }

    // -- Demand that produced no sale ----------------------------------

    public function test_a_failed_search_is_recorded(): void
    {
        FailedSearch::record('insulin glargine', null);

        $this->assertDatabaseHas('failed_searches', [
            'term' => 'INSULIN GLARGINE', 'times' => 1,
        ]);
    }

    public function test_repeat_searches_increment_rather_than_duplicate(): void
    {
        FailedSearch::record('ventolin inhaler');
        FailedSearch::record('Ventolin  Inhaler');   // different casing and spacing
        FailedSearch::record('VENTOLIN INHALER');

        $this->assertSame(1, FailedSearch::count(), 'The same term created duplicate rows.');
        $this->assertSame(3, (int) FailedSearch::first()->times);
    }

    public function test_short_fragments_are_ignored(): void
    {
        // The POS searches as you type, so these are mid-word, not real demand.
        FailedSearch::record('pa');
        FailedSearch::record('x');

        $this->assertSame(0, FailedSearch::count());
    }

    public function test_missed_demand_appears_on_the_dashboard(): void
    {
        FailedSearch::record('insulin glargine');
        FailedSearch::record('insulin glargine');

        $html = $this->dashboard()->html();

        $this->assertStringContainsString('Asked for, not stocked', $html);
        $this->assertStringContainsString('INSULIN GLARGINE', $html);
    }
    // -- Capture is opt-in ---------------------------------------------

    private function till(): \Livewire\Features\SupportTesting\Testable
    {
        $this->sell('PARACETAMOL', price: 160, cost: 85, qty: 1, sales: 1);

        return Livewire::actingAs(User::factory()->create(['role' => ['sales'], 'status' => 'active']))
            ->test(\App\Livewire\Pos\Index::class);
    }

    public function test_a_failed_search_is_not_recorded_on_its_own(): void
    {
        // Most failed searches are typos; logging them all buries the real ones.
        $this->till()->set('search', 'insulin glargine');

        $this->assertSame(0, FailedSearch::count(),
            'A search was recorded without anyone confirming it.');
    }

    public function test_it_offers_to_record_when_nothing_matches(): void
    {
        $c = $this->till()->set('search', 'insulin glargine');

        $c->assertSet('foundNothing', true);
        $this->assertStringContainsString('Did a customer actually ask for this?', $c->html());
    }

    public function test_tapping_yes_records_it(): void
    {
        $this->till()
            ->set('search', 'insulin glargine')
            ->call('noteMissedDemand');

        $this->assertDatabaseHas('failed_searches', ['term' => 'INSULIN GLARGINE', 'times' => 1]);
    }

    public function test_it_confirms_in_place_rather_than_interrupting(): void
    {
        $c = $this->till()
            ->set('search', 'insulin glargine')
            ->call('noteMissedDemand');

        $c->assertSet('justNoted', 'INSULIN GLARGINE');

        // The prompt turns into an inline confirmation in the same spot; the
        // question is gone and nothing was opened over the till.
        $this->assertStringContainsString('Noted', $c->html());
        $this->assertStringNotContainsString('Did a customer actually ask for this?', $c->html());
    }

    public function test_tapping_twice_does_not_double_count(): void
    {
        $this->till()
            ->set('search', 'insulin glargine')
            ->call('noteMissedDemand')
            ->call('noteMissedDemand');

        $this->assertSame(1, (int) FailedSearch::first()->times);
    }

    public function test_typing_again_clears_the_confirmation(): void
    {
        $this->till()
            ->set('search', 'insulin glargine')
            ->call('noteMissedDemand')
            ->set('search', 'paracetamol')
            ->assertSet('justNoted', '');
    }

    public function test_nothing_is_offered_when_a_product_matches(): void
    {
        $c = $this->till()->set('search', 'paracetamol');

        $c->assertSet('foundNothing', false);
        $this->assertStringNotContainsString('Did a customer actually ask for this?', $c->html());
    }

    public function test_a_typo_rescued_by_fuzzy_matching_offers_nothing(): void
    {
        // The drug was found, so there is no missed demand to record.
        $c = $this->till()->set('search', 'paracetmol');

        $c->assertSet('foundNothing', false);
    }
}