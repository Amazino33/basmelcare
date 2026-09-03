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
 * A product kept back from the shop must be kept back everywhere on it.
 *
 * Hiding it from the listing is not enough. Its own page has to refuse, and it
 * must not be addable to a basket by anybody who kept the link - otherwise the
 * setting is a suggestion rather than a decision, and a pharmacy that took
 * something offline deliberately would still be selling it.
 */
class HiddenProductsStayHiddenTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $name, bool $visible): Product
    {
        $product = Product::create([
            'name'          => $name,
            'category_id'   => Category::firstOrCreate(['name' => 'MEDICINE'])->id,
            'selling_price' => 100,
            'reorder_level' => 5,
            'show_in_shop'  => $visible,
        ]);

        Batch::create([
            'product_id'   => $product->id,
            'batch_number' => 'B' . random_int(1000, 9999),
            'expiry_date'  => now()->addYear(),
            'cost_price'   => 60,
            'quantity'     => 40,
        ]);

        return $product->fresh();
    }

    public function test_it_is_not_on_the_shop_front(): void
    {
        $this->product('KEPT BACK', false);
        $this->product('ON THE SHOP', true);

        Livewire::test(\App\Livewire\Shop\Home::class)
            ->assertSee('On The Shop')
            ->assertDontSee('Kept Back');
    }

    public function test_it_is_not_in_the_listing(): void
    {
        $this->product('KEPT BACK', false);

        Livewire::test(\App\Livewire\Shop\Index::class)->assertDontSee('Kept Back');
    }

    public function test_it_cannot_be_found_by_searching_for_it(): void
    {
        $this->product('KEPT BACK', false);

        Livewire::test(\App\Livewire\Shop\Index::class)
            ->set('search', 'KEPT BACK')
            ->assertDontSee('Kept Back');
    }

    public function test_it_cannot_be_added_to_a_basket(): void
    {
        // Somebody who kept the link, or a stale page in an open tab.
        $hidden = $this->product('KEPT BACK', false);

        Livewire::test(\App\Livewire\Shop\Home::class)->call('addToCart', $hidden->id);
        Livewire::test(\App\Livewire\Shop\Index::class)->call('addToCart', $hidden->id);

        $this->assertSame(0, (new CartService)->count());
    }

    public function test_a_category_it_was_the_only_thing_in_is_not_offered(): void
    {
        // A tab leading to an empty shelf is a dead end.
        $onlyHidden = Category::create(['name' => 'BEHIND THE COUNTER']);

        $product = $this->product('KEPT BACK', false);
        $product->update(['category_id' => $onlyHidden->id]);

        $names = Livewire::test(\App\Livewire\Shop\Home::class)->viewData('categories')->pluck('name');

        $this->assertFalse($names->contains('BEHIND THE COUNTER'));
    }

    public function test_a_visible_product_is_unaffected(): void
    {
        $visible = $this->product('ON THE SHOP', true);

        Livewire::test(\App\Livewire\Shop\Home::class)->call('addToCart', $visible->id);

        $this->assertSame(1, (new CartService)->count());
    }

    public function test_its_own_page_refuses_to_open(): void
    {
        // The link outlives the decision: a bookmark, a shared message, a tab
        // left open since before it was taken off.
        $hidden = $this->product('KEPT BACK', false);

        $this->get(route('shop.show', $hidden))->assertNotFound();
    }

    public function test_a_visible_product_page_still_opens(): void
    {
        $visible = $this->product('ON THE SHOP', true);

        $this->get(route('shop.show', $visible))->assertOk();
    }

    public function test_a_page_open_since_before_it_was_hidden_cannot_still_order(): void
    {
        // Loaded while it was on sale, hidden while the customer read it.
        $product = $this->product('ON THE SHOP', true);

        $page = Livewire::test(\App\Livewire\Shop\Show::class, ['product' => $product]);

        $product->update(['show_in_shop' => false]);

        $page->call('addToCart');
        $this->assertSame(0, (new CartService)->count());

        $page->call('buyNow');
        $this->assertSame(0, (new CartService)->count(), 'Buy now went round the check.');
    }
}
