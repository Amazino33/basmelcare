<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Selling and wholesale prices are a commercial decision limited to admin and
 * branch_manager. Everyone else keeps full product and stock management so a
 * delivery is never blocked — the product simply saves unpriced.
 */
class PriceLockdownTest extends TestCase
{
    use RefreshDatabase;

    private function product(float $price = 850): Product
    {
        return Product::create([
            'name'          => 'Paracetamol',
            'category_id'   => Category::create(['name' => 'Painkillers'])->id,
            'selling_price' => $price,
            'reorder_level' => 5,
        ]);
    }

    private function actAs(array $roles): User
    {
        $user = User::factory()->create(['role' => $roles, 'status' => 'active']);
        $this->actingAs($user);

        return $user;
    }

    public static function pricingRoles(): array
    {
        return ['admin' => [['admin']], 'branch_manager' => [['branch_manager']]];
    }

    public static function nonPricingRoles(): array
    {
        return [
            // Pharmacist is deliberately absent: they can no longer create or
            // edit products at all, so "can create but not price" no longer
            // describes them. See PharmacistCatalogueReadOnlyTest.
            'inventory_manager' => [['inventory_manager']],
        ];
    }

    #[DataProvider('pricingRoles')]
    public function test_pricing_roles_can_change_a_selling_price(array $roles): void
    {
        $this->actAs($roles);
        $product = $this->product(850);

        Livewire::test(\App\Livewire\Products\Index::class)
            ->call('editProduct', $product->id)
            ->set('selling_price', 1200)
            ->call('saveProduct');

        $this->assertEquals(1200, $product->fresh()->selling_price);
    }

    #[DataProvider('nonPricingRoles')]
    public function test_non_pricing_roles_cannot_change_a_selling_price(array $roles): void
    {
        $this->actAs($roles);
        $product = $this->product(850);

        Livewire::test(\App\Livewire\Products\Index::class)
            ->call('editProduct', $product->id)
            ->set('selling_price', 1)
            ->set('name', 'Paracetamol Forte')
            ->call('saveProduct');

        $fresh = $product->fresh();
        $this->assertEquals(850, $fresh->selling_price, 'Price was changed by a non-pricing role.');
        // Their other edits must still work — this is not a read-only lock.
        $this->assertSame('PARACETAMOL FORTE', $fresh->name);
    }

    #[DataProvider('nonPricingRoles')]
    public function test_non_pricing_roles_create_products_unpriced(array $roles): void
    {
        $this->actAs($roles);
        $category = Category::create(['name' => 'Antibiotics']);

        Livewire::test(\App\Livewire\Products\Index::class)
            ->call('createProduct')
            ->set('name', 'Amoxicillin 500mg')
            ->set('category_id', $category->id)
            ->set('selling_price', 5000)
            ->set('reorder_level', 3)
            ->call('saveProduct');

        // The form stores the name as typed; only the accessor uppercases it.
        $created = Product::whereRaw('UPPER(name) = ?', ['AMOXICILLIN 500MG'])->first();

        $this->assertNotNull($created, 'A delivery was blocked — the product could not be created.');
        $this->assertEquals(0, $created->selling_price, 'Price should save as 0 for a manager to set.');
    }

    #[DataProvider('nonPricingRoles')]
    public function test_correcting_a_batch_is_not_a_way_round_the_price_lock(array $roles): void
    {
        // Batch correction replaced bulk edit as the route for fixing a
        // mistyped delivery. It touches cost, never the selling price, so a
        // non-pricing role keeps it - but it must not become a side door.
        $this->actAs($roles);
        $product = $this->product(850);

        $batch = \App\Models\Batch::create([
            'product_id'   => $product->id,
            'batch_number' => 'B1',
            'expiry_date'  => now()->addYear(),
            'cost_price'   => 500,
            'quantity'     => 10,
        ]);

        Livewire::test(\App\Livewire\Products\Index::class)
            ->call('editBatch', $batch->id)
            ->set('edit_cost_price', 600)
            ->call('updateBatch');

        $this->assertEquals(600, $batch->fresh()->cost_price, 'A delivery cost could not be corrected.');
        $this->assertEquals(850, $product->fresh()->selling_price, 'The selling price moved with the cost.');
    }

    public function test_a_blocked_price_change_leaves_no_audit_entry(): void
    {
        $this->actAs(['inventory_manager']);
        $product = $this->product(850);
        AuditLog::query()->delete();

        Livewire::test(\App\Livewire\Products\Index::class)
            ->call('editProduct', $product->id)
            ->set('selling_price', 1)
            ->call('saveProduct');

        $this->assertSame(0, AuditLog::where('field', 'selling_price')->count());
    }

    public function test_an_allowed_price_change_is_audited(): void
    {
        $user    = $this->actAs(['branch_manager']);
        $product = $this->product(850);
        AuditLog::query()->delete();

        Livewire::test(\App\Livewire\Products\Index::class)
            ->call('editProduct', $product->id)
            ->set('selling_price', 1200)
            ->call('saveProduct');

        $log = AuditLog::where('field', 'selling_price')->first();

        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->user_id);
        $this->assertEquals(850, (float) $log->old_value);
        $this->assertEquals(1200, (float) $log->new_value);
    }
}
