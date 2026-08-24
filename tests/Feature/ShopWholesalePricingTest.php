<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shop is one catalogue seen by different people at different prices.
 *
 * The cart therefore stores only product and quantity. Price is read from the
 * product every time the cart is used, because a price frozen into the session
 * would outlive logging in, logging out, and any change of quantity - a basket
 * filled while signed in as a wholesaler could otherwise be checked out by
 * anybody, at wholesale prices.
 */
class ShopWholesalePricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Product::forgetDefaultMarkup();
        AppSetting::set('wholesale_markup_percent', 5);
        Product::forgetDefaultMarkup();
    }

    private function product(float $retail = 200, float $cost = 100, int $qty = 500, array $extra = []): Product
    {
        $product = Product::create(array_merge([
            'name'          => 'PARACETAMOL 500MG',
            'category_id'   => Category::firstOrCreate(['name' => 'General'])->id,
            'selling_price' => $retail,
            'reorder_level' => 1,
            'show_in_shop'  => true,
        ], $extra));

        Batch::create([
            'product_id'   => $product->id,
            'batch_number' => 'B-' . random_int(1000, 9999),
            'expiry_date'  => now()->addYear(),
            'cost_price'   => $cost,
            'quantity'     => $qty,
        ]);

        return $product->fresh();
    }

    private function customer(string $type): Customer
    {
        return Customer::create([
            'name'  => strtoupper($type) . ' BUYER',
            'type'  => $type,
            'phone' => '080' . random_int(10000000, 99999999),
        ]);
    }

    private function cart(): CartService
    {
        // A fresh instance each time: the service memoises within a request,
        // and each of these assertions stands for a separate one.
        return new CartService();
    }

    // ── what the catalogue shows ────────────────────────────────────────

    public function test_a_guest_sees_the_shelf_price(): void
    {
        $this->assertSame(200.0, $this->product()->shopPrice());
    }

    public function test_a_retail_customer_sees_the_shelf_price(): void
    {
        $this->actingAs($this->customer('retail'), 'customer');

        $this->assertSame(200.0, $this->product()->shopPrice());
    }

    public function test_a_wholesale_customer_sees_cost_plus_the_markup(): void
    {
        $this->actingAs($this->customer('wholesale'), 'customer');

        // 100 + 5%
        $this->assertSame(105.0, $this->product()->shopPrice());
    }

    public function test_the_shop_page_shows_the_wholesale_price(): void
    {
        $this->product();
        $this->actingAs($this->customer('wholesale'), 'customer');

        $this->get('/shop')
            ->assertOk()
            ->assertSee('105')          // charged
            ->assertSee('200');         // struck through, so the saving is visible
    }

    // ── the cart ────────────────────────────────────────────────────────

    public function test_the_cart_prices_for_the_logged_in_wholesaler(): void
    {
        $product = $this->product();
        $this->actingAs($this->customer('wholesale'), 'customer');

        $cart = $this->cart();
        $cart->add($product->id, 2);

        $this->assertSame(105.0, $cart->get()[(string) $product->id]['price']);
        $this->assertSame(210.0, $cart->subtotal());
    }

    public function test_a_basket_filled_by_a_wholesaler_reprices_once_they_log_out(): void
    {
        // The hole this design closes: a price stored in the session at
        // add-time would survive the logout and be charged to a guest.
        $product = $this->product();

        $this->actingAs($this->customer('wholesale'), 'customer');
        $this->cart()->add($product->id, 2);

        auth('customer')->logout();

        $this->assertSame(200.0, $this->cart()->get()[(string) $product->id]['price'],
            'A guest was served wholesale pricing left behind in the session.');
        $this->assertSame(400.0, $this->cart()->subtotal());
    }

    public function test_a_basket_filled_by_a_guest_reprices_once_they_log_in(): void
    {
        $product = $this->product();

        $this->cart()->add($product->id, 2);

        $this->actingAs($this->customer('wholesale'), 'customer');

        $this->assertSame(105.0, $this->cart()->get()[(string) $product->id]['price']);
    }

    public function test_reaching_the_wholesale_minimum_reprices_the_line(): void
    {
        // Price depends on quantity, so changing quantity must change price -
        // which a stored price would never do.
        $product = $this->product(extra: ['wholesale_min_qty' => 10]);

        $cart = $this->cart();
        $cart->add($product->id, 9);
        $this->assertSame(200.0, $cart->get()[(string) $product->id]['price']);

        $cart->update($product->id, 10);
        $this->assertSame(105.0, $cart->get()[(string) $product->id]['price']);
    }

    public function test_the_cart_carries_the_retail_price_for_comparison(): void
    {
        $product = $this->product();
        $this->actingAs($this->customer('wholesale'), 'customer');

        $cart = $this->cart();
        $cart->add($product->id, 2);

        $this->assertSame(400.0, $cart->retailSubtotal());
        $this->assertSame(210.0, $cart->subtotal());
        $this->assertTrue($cart->hasWholesalePricing());
    }

    // ── the cart still behaves as a cart ────────────────────────────────

    public function test_quantity_is_capped_at_available_stock(): void
    {
        $product = $this->product(qty: 3);

        $cart = $this->cart();
        $cart->add($product->id, 10);

        $this->assertSame(3, $cart->get()[(string) $product->id]['quantity']);
    }

    public function test_adding_the_same_product_twice_accumulates(): void
    {
        $product = $this->product();

        $cart = $this->cart();
        $cart->add($product->id, 2);
        $cart->add($product->id, 3);

        $this->assertSame(5, $cart->get()[(string) $product->id]['quantity']);
    }

    public function test_a_product_deleted_after_being_added_drops_out(): void
    {
        $product = $this->product();
        $this->cart()->add($product->id, 2);

        $product->delete();

        $this->assertSame([], $this->cart()->get());
        $this->assertSame(0.0, $this->cart()->subtotal());
    }

    public function test_removing_and_clearing_work(): void
    {
        $product = $this->product();

        $cart = $this->cart();
        $cart->add($product->id, 2);
        $cart->remove($product->id);
        $this->assertSame([], $cart->get());

        $cart->add($product->id, 2);
        $cart->clear();
        $this->assertSame([], $cart->get());
    }

    public function test_a_basket_saved_by_the_previous_version_is_read_forward(): void
    {
        // Old shape: a whole line per product, price included. Nobody's basket
        // should empty itself on deploy.
        $product = $this->product();

        session(['cart' => [
            (string) $product->id => [
                'product_id' => $product->id,
                'name'       => 'PARACETAMOL 500MG',
                'price'      => 999.0,     // stale, and must be ignored
                'quantity'   => 2,
                'max_stock'  => 500,
            ],
        ]]);

        $line = $this->cart()->get()[(string) $product->id];

        $this->assertSame(2, $line['quantity'], 'The old basket was lost.');
        $this->assertSame(200.0, $line['price'], 'A stale stored price was charged.');
    }

    public function test_prescription_flag_is_read_from_the_product_not_the_session(): void
    {
        $product = $this->product(extra: ['requires_prescription' => true]);

        $this->cart()->add($product->id, 1);

        $this->assertTrue($this->cart()->requiresPrescription());
    }
}
