<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Names are normalised on write so the stored value always matches what the UI
 * shows. Previously an accessor uppercased on read only, so a case-sensitive
 * query or an export disagreed with the screen.
 */
class ProductNameCasingTest extends TestCase
{
    use RefreshDatabase;

    private function category(): Category
    {
        return Category::create(['name' => 'Painkillers']);
    }

    private function storedName(int $id): string
    {
        return DB::table('products')->where('id', $id)->value('name');
    }

    public function test_name_is_stored_uppercase_not_just_displayed_uppercase(): void
    {
        $product = Product::create([
            'name' => 'Paracetamol 500mg',
            'category_id' => $this->category()->id,
            'selling_price' => 850,
            'reorder_level' => 1,
        ]);

        $this->assertSame('PARACETAMOL 500MG', $this->storedName($product->id));
        $this->assertSame('PARACETAMOL 500MG', $product->fresh()->name);
    }

    public function test_surrounding_whitespace_is_trimmed(): void
    {
        $product = Product::create([
            'name' => '  Ibuprofen  ',
            'category_id' => $this->category()->id,
            'selling_price' => 500,
            'reorder_level' => 1,
        ]);

        $this->assertSame('IBUPROFEN', $this->storedName($product->id));
    }

    public function test_a_case_sensitive_query_now_matches_the_screen(): void
    {
        $product = Product::create([
            'name' => 'Amoxicillin',
            'category_id' => $this->category()->id,
            'selling_price' => 1200,
            'reorder_level' => 1,
        ]);

        // This is the query that silently failed before the fix.
        $found = Product::where('name', $product->fresh()->name)->first();

        $this->assertNotNull($found, 'Displayed name does not match the stored name.');
        $this->assertSame($product->id, $found->id);
    }

    public function test_saving_through_the_product_form_normalises(): void
    {
        $this->actingAs(User::factory()->create(['role' => ['admin'], 'status' => 'active']));
        $category = $this->category();

        Livewire::test(\App\Livewire\Products\Index::class)
            ->call('createProduct')
            ->set('name', 'Vitamin c 1000mg')
            ->set('category_id', $category->id)
            ->set('selling_price', 300)
            ->set('reorder_level', 2)
            ->call('saveProduct');

        $product = Product::first();
        $this->assertSame('VITAMIN C 1000MG', $this->storedName($product->id));
    }

    public function test_bulk_edit_still_normalises_after_removing_its_own_strtoupper(): void
    {
        $this->actingAs(User::factory()->create(['role' => ['admin'], 'status' => 'active']));

        $product = Product::create([
            'name' => 'Aspirin',
            'category_id' => $this->category()->id,
            'selling_price' => 400,
            'reorder_level' => 1,
        ]);

        Livewire::test(\App\Livewire\Products\Index::class)
            ->set('bulkEdits', [
                $product->id => [
                    'name'          => 'aspirin dispersible',
                    'category_id'   => $product->category_id,
                    'selling_price' => 400,
                    'qty'           => 0,
                    'cost_price'    => 0,
                    'expiry_date'   => '',
                ],
            ])
            ->call('saveBulkEdits');

        $this->assertSame('ASPIRIN DISPERSIBLE', $this->storedName($product->id));
    }

    public function test_duplicate_detection_remains_case_insensitive(): void
    {
        $this->actingAs(User::factory()->create(['role' => ['admin'], 'status' => 'active']));
        $category = $this->category();

        Product::create([
            'name' => 'Paracetamol',
            'category_id' => $category->id,
            'selling_price' => 850,
            'reorder_level' => 1,
        ]);

        Livewire::test(\App\Livewire\Products\Index::class)
            ->call('createProduct')
            ->set('name', 'paracetamol')
            ->set('category_id', $category->id)
            ->set('selling_price', 900)
            ->set('reorder_level', 1)
            ->call('saveProduct')
            ->assertHasErrors('name');

        $this->assertSame(1, Product::count());
    }

    public function test_migration_backfills_mixed_case_rows(): void
    {
        $category = $this->category();

        // Write past the model to simulate rows created before the mutator.
        DB::table('products')->insert([
            'name' => 'Mixed Case Product',
            'category_id' => $category->id,
            'selling_price' => 100,
            'reorder_level' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id = DB::table('products')->where('name', 'Mixed Case Product')->value('id');
        $this->assertSame('Mixed Case Product', $this->storedName($id));

        (require database_path('migrations/2026_08_19_000003_normalise_product_name_casing.php'))->up();

        $this->assertSame('MIXED CASE PRODUCT', $this->storedName($id));
    }
}
