<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A struck-through price has to be one somebody actually pays.
 *
 * Seen on the live shop: every product showed "₦5 ₦3". Those products have a
 * wholesale minimum of 1, so the wholesale price is what everyone is charged -
 * and the ₦5 above it was a figure nobody had ever been asked for. A reference
 * price nobody pays is not a discount, it is a claim about a saving that did
 * not happen.
 */
class ShopPriceDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $overrides = []): Product
    {
        $product = Product::create(array_merge([
            'name'          => 'ASCORVIT 1000',
            'category_id'   => Category::firstOrCreate(['name' => 'MULTIVITAMINS'])->id,
            'selling_price' => 5,
            'wholesale_price' => 3,
            'reorder_level' => 5,
            'show_in_shop'  => true,
        ], $overrides));

        Batch::create([
            'product_id'   => $product->id,
            'batch_number' => 'B' . random_int(1000, 9999),
            'expiry_date'  => now()->addYear(),
            'cost_price'   => 2,
            'quantity'     => 500,
        ]);

        return $product->fresh();
    }

    private function customer(string $type): Customer
    {
        return Customer::create([
            'name' => 'ADAEZE OKON', 'type' => $type,
            'phone' => '080' . random_int(10000000, 99999999),
        ]);
    }

    public function test_a_stranger_is_shown_one_price(): void
    {
        // The exact case from the live site: a wholesale minimum of 1 means
        // everybody pays the wholesale price, so there is no saving to show.
        $this->product(['wholesale_min_qty' => 1]);

        $this->assertFalse($this->product(['wholesale_min_qty' => 1])->hasWholesaleDiscount());

        Livewire::test(\App\Livewire\Shop\Home::class)->assertDontSee('line-through', false);
    }

    public function test_a_retail_customer_is_shown_one_price(): void
    {
        $product = $this->product(['wholesale_min_qty' => 1]);

        $this->actingAs($this->customer('retail'), 'customer');

        $this->assertFalse($product->fresh()->hasWholesaleDiscount());
    }

    public function test_a_wholesale_customer_sees_what_they_are_saving(): void
    {
        // Here it is real: this person pays less than the shelf price because
        // of who they are, and showing the difference is the point.
        $product = $this->product();

        $this->actingAs($this->customer('wholesale'), 'customer');

        $this->assertTrue($product->fresh()->hasWholesaleDiscount());
        $this->assertEquals(3, $product->fresh()->shopPrice());
    }

    public function test_a_wholesale_customer_with_no_better_price_sees_no_saving(): void
    {
        // Their wholesale rate on this one is the shelf price. Nothing is
        // being saved, so nothing is struck through.
        $product = $this->product(['wholesale_price' => 5]);

        $this->actingAs($this->customer('wholesale'), 'customer');

        $this->assertEquals(5, $product->fresh()->shopPrice());
        $this->assertFalse($product->fresh()->hasWholesaleDiscount());
    }

    public function test_what_a_stranger_is_charged_has_not_changed(): void
    {
        // The display was wrong, not the price. Nobody's bill moves because of
        // this - changing what a live shop charges is not a display fix.
        $product = $this->product(['wholesale_min_qty' => 1]);

        $this->assertEquals(3, $product->shopPrice());
    }
}
