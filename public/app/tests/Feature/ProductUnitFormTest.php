<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Setting what one of a product is.
 *
 * The shop says "per tablet" so a bare ₦50 is not read as the price of the
 * whole box. Somebody has to be able to say which products that applies to,
 * and to change their mind.
 */
class ProductUnitFormTest extends TestCase
{
    use RefreshDatabase;

    private function product(?string $unit = null): Product
    {
        return Product::create([
            'name'          => 'PARACETAMOL 500MG',
            'unit'          => $unit,
            'category_id'   => Category::firstOrCreate(['name' => 'MEDICINE'])->id,
            'selling_price' => 50,
            'reorder_level' => 5,
        ]);
    }

    private function page()
    {
        return Livewire::actingAs(User::factory()->create(['role' => ['admin'], 'status' => 'active']))
            ->test(\App\Livewire\Products\Index::class);
    }

    public function test_it_can_be_set_on_a_product(): void
    {
        $product = $this->product();

        $this->page()
            ->call('editProduct', $product->id)
            ->set('unit', 'tablet')
            ->call('saveProduct')
            ->assertHasNoErrors();

        $this->assertSame('tablet', $product->fresh()->unit);
    }

    public function test_it_can_be_cleared_again(): void
    {
        // Most products are sold whole, and an empty box has to mean that
        // rather than being impossible to get back to.
        $product = $this->product('tablet');

        $this->page()
            ->call('editProduct', $product->id)
            ->set('unit', '')
            ->call('saveProduct');

        $this->assertNull($product->fresh()->unit);
    }

    public function test_the_form_loads_what_the_product_already_has(): void
    {
        $product = $this->product('capsule');

        $this->page()
            ->call('editProduct', $product->id)
            ->assertSet('unit', 'capsule');
    }

    public function test_it_does_not_carry_over_to_the_next_product(): void
    {
        // Opening a new product form after editing a tablet must not suggest
        // that the next thing is a tablet too.
        $product = $this->product('tablet');

        $this->page()
            ->call('editProduct', $product->id)
            ->call('createProduct')
            ->assertSet('unit', null);
    }

    public function test_the_choices_are_offered_on_the_form(): void
    {
        $this->page()->assertSee('Sold as');
    }
}
