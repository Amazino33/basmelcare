<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_product_via_add_product(): void
    {
        $admin = User::factory()->create(['role' => ['admin'], 'status' => 'active']);
        $category = Category::create(['name' => 'Antibiotics']);

        $test = Livewire::actingAs($admin)
            ->test(\App\Livewire\Products\Index::class)
            ->call('createProduct')
            ->set('name', 'Amoxicillin 500mg')
            ->set('category_id', $category->id)
            ->set('selling_price', '2500')
            ->call('saveProduct');

        $test->assertHasNoErrors();
        $test->assertSet('productModal', false);
        $this->assertDatabaseHas('products', ['name' => 'AMOXICILLIN 500MG']);
    }

    public function test_creating_product_with_cost_price_hint_auto_calculates_selling_price(): void
    {
        $admin = User::factory()->create(['role' => ['admin'], 'status' => 'active']);
        $category = Category::create(['name' => 'Vitamins']);

        $test = Livewire::actingAs($admin)
            ->test(\App\Livewire\Products\Index::class)
            ->call('createProduct')
            ->set('name', 'Vitamin C 500mg')
            ->set('category_id', $category->id)
            ->set('cost_price_hint', '1000')
            ->call('saveProduct');

        $test->assertHasNoErrors();
        $this->assertDatabaseHas('products', ['name' => 'VITAMIN C 500MG']);
    }

    public function test_inventory_manager_can_create_unpriced_product(): void
    {
        $im = User::factory()->create(['role' => ['inventory_manager'], 'status' => 'active']);
        $category = Category::create(['name' => 'Syrups']);

        $test = Livewire::actingAs($im)
            ->test(\App\Livewire\Products\Index::class)
            ->call('createProduct')
            ->set('name', 'Cough Syrup 100ml')
            ->set('category_id', $category->id)
            ->call('saveProduct');

        $test->assertHasNoErrors();
        $test->assertSet('productModal', false);
        $this->assertDatabaseHas('products', [
            'name' => 'COUGH SYRUP 100ML',
            'selling_price' => 0,
        ]);
    }

    public function test_blank_reorder_level_defaults_to_zero(): void
    {
        $admin = User::factory()->create(['role' => ['admin'], 'status' => 'active']);
        $category = Category::create(['name' => 'Topical']);

        $test = Livewire::actingAs($admin)
            ->test(\App\Livewire\Products\Index::class)
            ->call('createProduct')
            ->set('name', 'Hydrocortisone Cream')
            ->set('category_id', $category->id)
            ->set('selling_price', '1200')
            ->set('reorder_level', '')
            ->call('saveProduct');

        $test->assertHasNoErrors();
        $test->assertSet('productModal', false);
        $this->assertDatabaseHas('products', [
            'name' => 'HYDROCORTISONE CREAM',
            'reorder_level' => 0,
        ]);
    }
}
