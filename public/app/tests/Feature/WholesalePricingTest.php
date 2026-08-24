<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Customers tagged as wholesale pay cost plus a markup, rather than retail.
 *
 * "Cost" is the dearest batch still holding stock - not the latest, not the
 * average. Stock arrives at different prices, and pricing off an older cheaper
 * batch sells goods for less than it costs to replace them: a profit on paper
 * and a loss in the drawer.
 */
class WholesalePricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Product::forgetDefaultMarkup();
    }

    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name'          => 'PARACETAMOL 500MG',
            'category_id'   => Category::firstOrCreate(['name' => 'General'])->id,
            'selling_price' => 200,
            'reorder_level' => 1,
        ], $attributes));
    }

    private function stock(Product $product, float $cost, int $qty): Batch
    {
        return Batch::create([
            'product_id'   => $product->id,
            'batch_number' => 'B-' . random_int(1000, 9999),
            'expiry_date'  => now()->addYear(),
            'cost_price'   => $cost,
            'quantity'     => $qty,
        ]);
    }

    private function customer(string $type): Customer
    {
        return Customer::create([
            'name'  => strtoupper($type) . ' BUYER',
            'type'  => $type,
            'phone' => '080' . random_int(10000000, 99999999),
        ]);
    }

    // ── which cost the price is based on ────────────────────────────────

    public function test_the_price_comes_from_the_dearest_batch_in_stock(): void
    {
        $product = $this->product();
        $this->stock($product, cost: 85, qty: 40);    // older, cheaper
        $this->stock($product, cost: 110, qty: 200);  // newer, dearer

        // 110 + 5% = 115.50. Off the cheap batch it would be 89.25, which is
        // below what the next delivery costs.
        $this->assertSame(115.50, $product->fresh()->getPriceFor($this->customer('wholesale')));
    }

    public function test_empty_batches_do_not_hold_the_price_up(): void
    {
        $product = $this->product();
        $this->stock($product, cost: 500, qty: 0);   // sold out, dear
        $this->stock($product, cost: 100, qty: 50);  // what is actually on the shelf

        $this->assertSame(105.0, $product->fresh()->getPriceFor($this->customer('wholesale')));
    }

    public function test_with_nothing_in_stock_it_falls_back_to_retail(): void
    {
        // No cost to work from means no honest wholesale price. Falling back to
        // retail is the safe direction to be wrong in - guessing one risks
        // selling below cost.
        $product = $this->product(['selling_price' => 200]);

        $this->assertSame(200.0, $product->getPriceFor($this->customer('wholesale')));
    }

    // ── who gets it ─────────────────────────────────────────────────────

    public function test_a_retail_customer_pays_retail(): void
    {
        $product = $this->product(['selling_price' => 200]);
        $this->stock($product, cost: 100, qty: 50);

        $this->assertSame(200.0, $product->fresh()->getPriceFor($this->customer('retail')));
    }

    public function test_a_walk_in_with_no_customer_record_pays_retail(): void
    {
        $product = $this->product(['selling_price' => 200]);
        $this->stock($product, cost: 100, qty: 50);

        $this->assertSame(200.0, $product->fresh()->getPriceFor(null));
    }

    public function test_a_retail_customer_buying_in_bulk_gets_the_wholesale_price(): void
    {
        // Existing behaviour, preserved: quantity alone can qualify.
        $product = $this->product(['wholesale_min_qty' => 10]);
        $this->stock($product, cost: 100, qty: 500);

        $product = $product->fresh();

        $this->assertSame(200.0, $product->getPriceFor($this->customer('retail'), 9));
        $this->assertSame(105.0, $product->getPriceFor($this->customer('retail'), 10));
    }

    // ── manual price wins ───────────────────────────────────────────────

    public function test_a_price_typed_in_by_hand_overrides_the_calculation(): void
    {
        $product = $this->product(['wholesale_price' => 150]);
        $this->stock($product, cost: 100, qty: 50);

        $this->assertSame(150.0, $product->fresh()->getPriceFor($this->customer('wholesale')));
    }

    // ── the markup ──────────────────────────────────────────────────────

    public function test_the_pharmacy_default_is_used_when_the_product_says_nothing(): void
    {
        AppSetting::set('wholesale_markup_percent', 12);
        Product::forgetDefaultMarkup();

        $product = $this->product();
        $this->stock($product, cost: 100, qty: 50);

        $this->assertSame(112.0, $product->fresh()->getPriceFor($this->customer('wholesale')));
    }

    public function test_a_product_can_override_the_default(): void
    {
        AppSetting::set('wholesale_markup_percent', 5);
        Product::forgetDefaultMarkup();

        $product = $this->product(['wholesale_markup_percent' => 20]);
        $this->stock($product, cost: 100, qty: 50);

        $this->assertSame(120.0, $product->fresh()->getPriceFor($this->customer('wholesale')));
    }

    public function test_a_zero_override_means_sell_at_cost_not_use_the_default(): void
    {
        // Null and zero must not be conflated: one defers to the pharmacy
        // default, the other is a deliberate decision to take no margin.
        AppSetting::set('wholesale_markup_percent', 5);
        Product::forgetDefaultMarkup();

        $product = $this->product(['wholesale_markup_percent' => 0]);
        $this->stock($product, cost: 100, qty: 50);

        $this->assertSame(100.0, $product->fresh()->getPriceFor($this->customer('wholesale')));
    }

    // ── the settings screen ─────────────────────────────────────────────

    private function admin(): User
    {
        return User::factory()->create(['role' => ['admin'], 'status' => 'active']);
    }

    public function test_the_markup_can_be_changed_and_takes_effect_at_once(): void
    {
        $product = $this->product();
        $this->stock($product, cost: 100, qty: 50);

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Settings\Index::class)
            ->set('wholesale_markup_percent', 8)
            ->call('saveWholesalePricing')
            ->assertHasNoErrors();

        $this->assertSame(108.0, $product->fresh()->getPriceFor($this->customer('wholesale')),
            'The memoised default was not cleared after the setting changed.');
    }

    public function test_an_absurd_markup_is_rejected(): void
    {
        // A three-digit figure here is nearly always a typo, and it would
        // reprice the whole catalogue for every wholesale customer.
        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Settings\Index::class)
            ->set('wholesale_markup_percent', 5000)
            ->call('saveWholesalePricing')
            ->assertHasErrors('wholesale_markup_percent');
    }

    // ── the product form ────────────────────────────────────────────────

    public function test_the_override_can_be_left_blank_to_mean_use_the_default(): void
    {
        AppSetting::set('wholesale_markup_percent', 5);
        Product::forgetDefaultMarkup();

        $product = $this->product(['wholesale_markup_percent' => 30]);

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Products\Index::class)
            ->call('editProduct', $product->id)
            ->set('wholesale_markup_percent', '')
            ->call('saveProduct');

        $this->assertNull($product->fresh()->wholesale_markup_percent);
        $this->assertSame(5.0, $product->fresh()->wholesaleMarkupPercent());
    }

    public function test_the_product_list_does_not_query_per_row(): void
    {
        // calculatedWholesalePrice() is rendered for every row. Without using
        // the eager-loaded batches this puts the page back to a query each.
        foreach (range(1, 8) as $i) {
            $product = $this->product(['name' => "DRUG {$i}"]);
            $this->stock($product, cost: 100, qty: 10);
        }

        \Illuminate\Support\Facades\DB::enableQueryLog();

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Products\Index::class)
            ->assertOk();

        $queries = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $this->assertLessThan(40, $queries,
            "Rendering 8 products ran {$queries} queries - the batch lookup is not using the loaded relation.");
    }
}
