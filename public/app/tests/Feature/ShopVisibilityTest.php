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
 * Choosing what the public shop shows.
 *
 * The column existed and defaulted to on, so every product a pharmacy had ever
 * entered was published, and there was no control anywhere to change it. That
 * is the wrong default to be stuck with in a pharmacy: some things are sold
 * over the counter on advice, and some a shop would simply rather strangers
 * did not order.
 *
 * The default is left alone deliberately - changing it now would empty the
 * shop of everything already on it.
 */
class ShopVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $roles): User
    {
        return User::factory()->create(['role' => $roles, 'status' => 'active']);
    }

    private function product(string $name = 'PARACETAMOL 500MG', ?bool $visible = null): Product
    {
        $product = Product::create(array_filter([
            'name'          => $name,
            'category_id'   => Category::firstOrCreate(['name' => 'MEDICINE'])->id,
            'selling_price' => 100,
            'reorder_level' => 5,
            'show_in_shop'  => $visible,
        ], fn ($v) => $v !== null));

        Batch::create([
            'product_id'   => $product->id,
            'batch_number' => 'B' . random_int(1000, 9999),
            'expiry_date'  => now()->addYear(),
            'cost_price'   => 60,
            'quantity'     => 40,
        ]);

        return $product->fresh();
    }

    private function page(?User $as = null)
    {
        return Livewire::actingAs($as ?? $this->user(['admin']))
            ->test(\App\Livewire\Products\Index::class);
    }

    // ── the control ─────────────────────────────────────────────────────

    public function test_a_product_is_published_unless_somebody_says_otherwise(): void
    {
        // The catalogue already behaves this way and thousands of products
        // depend on it. Changing the default would empty the shop.
        $this->assertTrue((bool) $this->product()->show_in_shop);
    }

    public function test_it_can_be_taken_off_the_shop(): void
    {
        $product = $this->product();

        $this->page()
            ->call('editProduct', $product->id)
            ->set('show_in_shop', false)
            ->call('saveProduct')
            ->assertHasNoErrors();

        $this->assertFalse((bool) $product->fresh()->show_in_shop);
    }

    public function test_it_can_be_put_back(): void
    {
        $product = $this->product(visible: false);

        $this->page()
            ->call('editProduct', $product->id)
            ->set('show_in_shop', true)
            ->call('saveProduct');

        $this->assertTrue((bool) $product->fresh()->show_in_shop);
    }

    public function test_the_form_shows_what_the_product_already_is(): void
    {
        $product = $this->product(visible: false);

        $this->page()
            ->call('editProduct', $product->id)
            ->assertSet('show_in_shop', false);
    }

    public function test_a_new_product_starts_published(): void
    {
        // Even after editing a hidden one, so the form does not carry the last
        // product's answer into the next.
        $hidden = $this->product(visible: false);

        $this->page()
            ->call('editProduct', $hidden->id)
            ->call('createProduct')
            ->assertSet('show_in_shop', true);
    }

    // ── doing it without opening the form ───────────────────────────────

    public function test_it_can_be_switched_from_the_list(): void
    {
        // Through a modal, one product at a time, this is not a feature
        // anybody would use on a catalogue of any size.
        $product = $this->product();

        $this->page()->call('toggleShopVisibility', $product->id);

        $this->assertFalse((bool) $product->fresh()->show_in_shop);
    }

    public function test_switching_it_twice_puts_it_back(): void
    {
        $product = $this->product();

        $this->page()->call('toggleShopVisibility', $product->id);
        $this->page()->call('toggleShopVisibility', $product->id);

        $this->assertTrue((bool) $product->fresh()->show_in_shop);
    }

    public function test_the_list_can_be_filtered_to_what_is_hidden(): void
    {
        $this->product('ON THE SHOP');
        $this->product('KEPT BACK', visible: false);

        $page = $this->page()->set('shopFilter', 'hidden');

        $this->assertSame(1, $page->viewData('products')->total());
        $page->assertSee('KEPT BACK')->assertDontSee('ON THE SHOP');
    }

    public function test_the_list_can_be_filtered_to_what_is_published(): void
    {
        $this->product('ON THE SHOP');
        $this->product('KEPT BACK', visible: false);

        $page = $this->page()->set('shopFilter', 'visible');

        $this->assertSame(1, $page->viewData('products')->total());
        $page->assertSee('ON THE SHOP')->assertDontSee('KEPT BACK');
    }

    public function test_the_counts_say_how_many_of_each(): void
    {
        $this->product('ONE');
        $this->product('TWO');
        $this->product('HIDDEN', visible: false);

        $page = $this->page();

        $this->assertSame(2, $page->viewData('onShopCount'));
        $this->assertSame(1, $page->viewData('hiddenCount'));
    }

    // ── who may change it ───────────────────────────────────────────────

    public function test_a_pharmacist_cannot_change_what_the_shop_shows(): void
    {
        // They read the catalogue and do not edit it - the same rule as every
        // other product field, checked in the action rather than by hiding the
        // button.
        $product = $this->product();

        $this->page($this->user(['pharmacist']))->call('toggleShopVisibility', $product->id);

        $this->assertTrue((bool) $product->fresh()->show_in_shop);
    }

    public function test_an_inventory_manager_can(): void
    {
        $product = $this->product();

        $this->page($this->user(['inventory_manager']))->call('toggleShopVisibility', $product->id);

        $this->assertFalse((bool) $product->fresh()->show_in_shop);
    }
}
