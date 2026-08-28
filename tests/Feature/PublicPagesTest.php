<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The pages a customer can reach without an account.
 *
 * Thin on purpose: this asserts that each one answers, not how it looks. What
 * a page is allowed to show is tested where that rule lives - pricing in
 * ShopWholesalePricingTest, booking in ConsultationSelfBookingTest.
 */
class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        return Product::create([
            'name'          => 'PARACETAMOL 500MG',
            'category_id'   => Category::create(['name' => 'PAINKILLERS'])->id,
            'selling_price' => 850,
            'reorder_level' => 5,
        ]);
    }

    public function test_the_home_page_opens(): void
    {
        $this->get(route('home'))->assertOk();
    }

    public function test_the_shop_opens_with_nothing_in_stock(): void
    {
        // An empty catalogue is a normal state on a fresh install, and it must
        // not be the thing that breaks the front page.
        $this->get(route('shop.index'))->assertOk();
    }

    public function test_a_product_page_opens(): void
    {
        $this->get(route('shop.show', $this->product()))->assertOk();
    }

    public function test_the_cart_and_checkout_open_without_an_account(): void
    {
        // Guest checkout is deliberate: making people register before they can
        // buy medicine loses the sale.
        $this->get(route('cart'))->assertOk();
        $this->get(route('checkout'))->assertOk();
    }

    public function test_consultation_booking_opens_to_anyone(): void
    {
        $this->get(route('consultation.book'))->assertOk();
    }

    public function test_an_unknown_page_is_a_404_and_not_a_crash(): void
    {
        $this->get('/no-such-page')->assertNotFound();
    }
}
