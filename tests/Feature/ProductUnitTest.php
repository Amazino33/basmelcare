<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Saying what a price actually buys.
 *
 * A bare ₦50 beside a picture of a box of paracetamol reads as the price of
 * the box. On anything broken out of its packet and sold one at a time, the
 * shop has to say "per tablet" or the number is worse than useless - it looks
 * like a mistake, or a bargain, and neither brings anybody to the counter in a
 * good mood.
 */
class ProductUnitTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $overrides = []): Product
    {
        $product = Product::create(array_merge([
            'name'          => 'PARACETAMOL 500MG',
            'category_id'   => Category::firstOrCreate(['name' => 'MEDICINE'])->id,
            'selling_price' => 50,
            'reorder_level' => 5,
            'show_in_shop'  => true,
        ], $overrides));

        Batch::create([
            'product_id'   => $product->id,
            'batch_number' => 'B' . random_int(1000, 9999),
            'expiry_date'  => now()->addYear(),
            'cost_price'   => 30,
            'quantity'     => 200,
        ]);

        return $product->fresh();
    }

    // ── the words themselves ────────────────────────────────────────────

    public function test_a_loose_product_says_what_one_of_them_is(): void
    {
        $product = $this->product(['unit' => 'tablet']);

        $this->assertSame('tablet', $product->unitLabel());
        $this->assertSame('per tablet', $product->priceUnitLabel());
    }

    public function test_it_pluralises_for_a_quantity(): void
    {
        $product = $this->product(['unit' => 'tablet']);

        $this->assertSame('tablets', $product->unitLabel(3));
        $this->assertSame('capsules', $this->product(['unit' => 'capsule'])->unitLabel(10));
    }

    public function test_a_whole_item_says_nothing(): void
    {
        // Most products are sold whole. "1 each" beside a bottle of syrup is
        // noise, and worse than a plain price.
        $product = $this->product(['unit' => null]);

        $this->assertNull($product->unitLabel());
        $this->assertNull($product->priceUnitLabel());
    }

    public function test_a_unit_that_is_not_on_the_list_is_ignored(): void
    {
        // The list is fixed so "tablet", "Tablets" and "tabs" cannot all end up
        // on the shop for the same kind of thing. Anything else is treated as
        // unset rather than printed raw.
        $product = $this->product(['unit' => 'whatever']);

        $this->assertNull($product->unitLabel());
    }

    public function test_a_pack_is_described_in_the_same_words(): void
    {
        $product = $this->product([
            'unit' => 'tablet', 'has_pack' => true, 'pack_size' => 10, 'pack_price' => 400,
        ]);

        $this->assertSame('Pack of 10 tablets', $product->packLabel());
    }

    public function test_a_pack_of_something_with_no_unit_still_reads_properly(): void
    {
        $product = $this->product([
            'unit' => null, 'has_pack' => true, 'pack_size' => 6, 'pack_price' => 900,
        ]);

        $this->assertSame('Pack of 6', $product->packLabel());
    }

    public function test_nothing_is_said_about_a_pack_that_does_not_exist(): void
    {
        $this->assertNull($this->product(['unit' => 'tablet'])->packLabel());
    }

    // ── where the customer meets it ─────────────────────────────────────

    public function test_the_shop_front_says_per_tablet(): void
    {
        $this->product(['unit' => 'tablet']);

        Livewire::test(\App\Livewire\Shop\Home::class)->assertSee('per tablet');
    }

    public function test_the_shop_listing_says_per_tablet(): void
    {
        $this->product(['unit' => 'tablet']);

        Livewire::test(\App\Livewire\Shop\Index::class)->assertSee('per tablet');
    }

    public function test_the_product_page_says_per_tablet(): void
    {
        $product = $this->product(['unit' => 'tablet']);

        $this->get(route('shop.show', $product))
            ->assertOk()
            ->assertSee('per tablet');
    }

    public function test_the_product_page_offers_the_pack_and_its_price(): void
    {
        // Somebody wanting a full course should not have to work out that ten
        // of these is a packet.
        $product = $this->product([
            'unit' => 'tablet', 'has_pack' => true, 'pack_size' => 10, 'pack_price' => 400,
        ]);

        $this->get(route('shop.show', $product))
            ->assertOk()
            ->assertSee('Pack of 10 tablets')
            ->assertSee('400');
    }

    public function test_the_basket_counts_in_tablets(): void
    {
        $product = $this->product(['unit' => 'tablet']);
        (new CartService)->add($product->id, 3);

        Livewire::test(\App\Livewire\Shop\Cart::class)
            ->assertSee('tablets')
            ->assertSee('per tablet');
    }

    public function test_a_whole_item_is_left_alone_everywhere(): void
    {
        // The shop should look exactly as it did for the things that do not
        // need this.
        $this->product(['name' => 'COUGH SYRUP 100ML', 'unit' => null]);

        Livewire::test(\App\Livewire\Shop\Home::class)->assertDontSee('per each');
    }
}
