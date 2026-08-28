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
 * The pharmacist must see the catalogue to know what can be dispensed, but
 * maintaining it is inventory work. Every write is blocked in the ACTION, not
 * merely by hiding a button - a Livewire method stays callable either way.
 */
class PharmacistCatalogueReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    private function url(string $path): string
    {
        return '/' . trim(config('app.desk_prefix'), '/') . '/' . ltrim($path, '/');
    }

    private function pharmacist(): User
    {
        return User::factory()->create(['role' => ['pharmacist'], 'status' => 'active']);
    }

    private function product(string $name = 'PARACETAMOL 500MG'): Product
    {
        $product = Product::create([
            'name' => $name,
            'category_id' => Category::firstOrCreate(['name' => 'Painkillers'])->id,
            'selling_price' => 850, 'reorder_level' => 1,
        ]);

        Batch::create([
            'product_id' => $product->id, 'batch_number' => 'B1',
            'expiry_date' => now()->addYear(), 'cost_price' => 500, 'quantity' => 20,
        ]);

        return $product;
    }

    // -- Still visible -------------------------------------------------

    public function test_pharmacist_can_still_open_products_and_categories(): void
    {
        $this->actingAs($this->pharmacist());

        $this->get($this->url('products'))->assertOk();
        $this->get($this->url('categories'))->assertOk();
    }

    public function test_pharmacist_sees_what_is_in_stock(): void
    {
        $this->product('AMOXICILLIN 500MG');

        $html = $this->actingAs($this->pharmacist())->get($this->url('products'))->getContent();

        // The whole point: knowing what can be dispensed.
        $this->assertStringContainsString('AMOXICILLIN 500MG', $html);
    }

    public function test_pharmacist_can_still_inspect_batches(): void
    {
        $product = $this->product();
        $this->actingAs($this->pharmacist());

        Livewire::test(\App\Livewire\Products\Index::class)
            ->call('viewBatches', $product->id)
            ->assertSet('batchesDrawer', true);
    }

    // -- Writes blocked ------------------------------------------------

    public function test_pharmacist_cannot_create_a_product(): void
    {
        $this->actingAs($this->pharmacist());
        $category = Category::firstOrCreate(['name' => 'Painkillers']);

        Livewire::test(\App\Livewire\Products\Index::class)
            ->set('name', 'SNEAKY DRUG')
            ->set('category_id', $category->id)
            ->set('selling_price', 100)
            ->set('reorder_level', 1)
            ->call('saveProduct');

        $this->assertSame(0, Product::where('name', 'SNEAKY DRUG')->count());
    }

    public function test_pharmacist_cannot_delete_a_product(): void
    {
        $product = $this->product();
        $this->actingAs($this->pharmacist());

        Livewire::test(\App\Livewire\Products\Index::class)->call('deleteProduct', $product->id);

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_pharmacist_cannot_open_the_edit_form(): void
    {
        $product = $this->product();
        $this->actingAs($this->pharmacist());

        Livewire::test(\App\Livewire\Products\Index::class)
            ->call('editProduct', $product->id)
            ->assertSet('productModal', false);
    }

    public function test_pharmacist_cannot_save_a_product_even_by_calling_it_directly(): void
    {
        // The form never opens for them, but a closed form is not a lock -
        // nothing stops the action being called from the console.
        $product = $this->product();
        $this->actingAs($this->pharmacist());

        Livewire::test(\App\Livewire\Products\Index::class)
            ->set('productId', $product->id)
            ->set('name', 'RENAMED')
            ->set('category_id', $product->category_id)
            ->set('selling_price', 1)
            ->set('reorder_level', 1)
            ->call('saveProduct');

        $this->assertSame('PARACETAMOL 500MG', $product->fresh()->name);
        $this->assertEquals(850, $product->fresh()->selling_price);
    }

    public function test_pharmacist_cannot_add_stock(): void
    {
        $product = $this->product();
        $this->actingAs($this->pharmacist());

        Livewire::test(\App\Livewire\Products\Index::class)
            ->call('openBatchModal', $product->id)
            ->assertSet('batchModal', false);
    }

    public function test_pharmacist_cannot_import_products(): void
    {
        $this->actingAs($this->pharmacist());

        Livewire::test(\App\Livewire\Products\Index::class)
            ->call('openImport')
            ->assertSet('importModal', false);
    }

    public function test_pharmacist_cannot_quick_add(): void
    {
        $this->actingAs($this->pharmacist());

        Livewire::test(\App\Livewire\Products\Index::class)
            ->call('openQuickAdd')
            ->assertSet('quickModal', false);
    }

    public function test_pharmacist_cannot_correct_a_batch(): void
    {
        // Cost and expiry are inventory's record of what was bought. A
        // pharmacist reads them; changing one would rewrite the money trail.
        $product = $this->product();
        $batch   = \App\Models\Batch::create([
            'product_id'   => $product->id,
            'batch_number' => 'B1',
            'expiry_date'  => now()->addYear(),
            'cost_price'   => 500,
            'quantity'     => 10,
        ]);

        $this->actingAs($this->pharmacist());

        Livewire::test(\App\Livewire\Products\Index::class)
            ->call('editBatch', $batch->id)
            ->assertSet('editingBatchId', null)
            ->set('editingBatchId', $batch->id)
            ->set('edit_batch_number', 'B1')
            ->set('edit_cost_price', 9)
            ->set('edit_expiry_date', now()->addYear()->format('Y-m-d'))
            ->call('updateBatch');

        $this->assertEquals(500, $batch->fresh()->cost_price);
    }

    public function test_pharmacist_cannot_create_or_delete_a_category(): void
    {
        $category = Category::create(['name' => 'ANTIBIOTICS']);
        $this->actingAs($this->pharmacist());

        Livewire::test(\App\Livewire\Categories\Index::class)
            ->set('name', 'SNEAKY CATEGORY')
            ->call('save');
        $this->assertSame(0, Category::where('name', 'SNEAKY CATEGORY')->count());

        Livewire::test(\App\Livewire\Categories\Index::class)->call('delete', $category->id);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    // -- Controls hidden too -------------------------------------------

    public function test_the_edit_controls_are_not_shown_to_a_pharmacist(): void
    {
        $this->product();
        $html = $this->actingAs($this->pharmacist())->get($this->url('products'))->getContent();

        // Assert on the click handlers, not the labels: the modals themselves
        // are always present in the DOM (merely closed), so their titles match
        // even when no button can open them.
        foreach ([
            'createProduct', 'openQuickAdd', 'editBatch', 'openImport',
            'editProduct', 'deleteProduct', 'openBatchModal',
        ] as $writeAction) {
            $this->assertStringNotContainsString($writeAction, $html,
                "The {$writeAction} control was rendered for a pharmacist.");
        }

        // ...while the read-only control is still offered.
        $this->assertStringContainsString('viewBatches', $html);
    }

    // -- Inventory roles unaffected ------------------------------------

    public function test_inventory_manager_can_still_maintain_the_catalogue(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => ['inventory_manager'], 'status' => 'active',
        ]));

        Livewire::test(\App\Livewire\Categories\Index::class)
            ->set('name', 'NEW CATEGORY')
            ->call('save');

        $this->assertSame(1, Category::where('name', 'NEW CATEGORY')->count());
    }

    public function test_a_pharmacist_who_is_also_inventory_keeps_edit_rights(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => ['pharmacist', 'inventory_manager'], 'status' => 'active',
        ]));

        $this->assertTrue(
            Livewire::test(\App\Livewire\Products\Index::class)->instance()->canEditCatalogue()
        );
    }
}
