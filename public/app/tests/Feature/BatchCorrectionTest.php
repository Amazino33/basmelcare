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
 * Correcting a mistyped delivery.
 *
 * Bulk edit used to be the only way to change a batch's cost or expiry after
 * it was created. Removing it would have left a typo uncorrectable through
 * the interface - and cost matters more than it used to, because wholesale
 * prices are derived from the dearest batch in stock, so a wrong cost quietly
 * misprices every wholesale sale of that drug.
 *
 * Quantity is deliberately absent. Changing how much stock exists is a
 * physical claim that needs a reason and a note, which Stock Adjustments
 * already asks for; fixing a typing error is a different act.
 */
class BatchCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $roles): User
    {
        return User::factory()->create(['role' => $roles, 'status' => 'active']);
    }

    private function batch(float $cost = 85, int $qty = 120): Batch
    {
        $product = Product::create([
            'name'          => 'PARACETAMOL 500MG',
            'category_id'   => Category::firstOrCreate(['name' => 'General'])->id,
            'selling_price' => 200,
            'reorder_level' => 1,
        ]);

        return Batch::create([
            'product_id'   => $product->id,
            'batch_number' => 'B-2291',
            'expiry_date'  => now()->addYear()->toDateString(),
            'cost_price'   => $cost,
            'quantity'     => $qty,
        ]);
    }

    private function page(User $user)
    {
        return Livewire::actingAs($user)->test(\App\Livewire\Products\Index::class);
    }

    // ── the correction ──────────────────────────────────────────────────

    public function test_a_mistyped_cost_can_be_corrected(): void
    {
        $batch = $this->batch(cost: 8.5);   // meant 85

        $this->page($this->user(['inventory_manager']))
            ->call('editBatch', $batch->id)
            ->set('edit_cost_price', '85')
            ->call('updateBatch');

        $this->assertEquals(85, $batch->fresh()->cost_price);
    }

    public function test_an_expiry_date_can_be_corrected(): void
    {
        $batch = $this->batch();

        $this->page($this->user(['inventory_manager']))
            ->call('editBatch', $batch->id)
            ->set('edit_expiry_date', '2027-03-31')
            ->call('updateBatch');

        $this->assertSame('2027-03-31', $batch->fresh()->expiry_date->toDateString());
    }

    public function test_the_batch_number_can_be_corrected(): void
    {
        $batch = $this->batch();

        $this->page($this->user(['inventory_manager']))
            ->call('editBatch', $batch->id)
            ->set('edit_batch_number', 'B-2219')
            ->call('updateBatch');

        $this->assertSame('B-2219', $batch->fresh()->batch_number);
    }

    public function test_quantity_is_not_touched(): void
    {
        // Stock levels are a physical claim. This screen fixes typing errors,
        // and must not become a quiet way to change how much stock exists.
        $batch = $this->batch(qty: 120);

        $this->page($this->user(['inventory_manager']))
            ->call('editBatch', $batch->id)
            ->set('edit_cost_price', '90')
            ->call('updateBatch');

        $this->assertSame(120, (int) $batch->fresh()->quantity);
    }

    public function test_the_change_is_recorded_against_whoever_made_it(): void
    {
        $user  = $this->user(['inventory_manager']);
        $batch = $this->batch(cost: 85);

        $this->page($user)
            ->call('editBatch', $batch->id)
            ->set('edit_cost_price', '95')
            ->call('updateBatch');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'field'   => 'cost_price',
        ]);
    }

    // ── validation ──────────────────────────────────────────────────────

    public function test_a_negative_cost_is_rejected(): void
    {
        $batch = $this->batch(cost: 85);

        $this->page($this->user(['inventory_manager']))
            ->call('editBatch', $batch->id)
            ->set('edit_cost_price', '-5')
            ->call('updateBatch')
            ->assertHasErrors('edit_cost_price');

        $this->assertEquals(85, $batch->fresh()->cost_price);
    }

    public function test_an_empty_expiry_is_rejected(): void
    {
        $batch = $this->batch();

        $this->page($this->user(['inventory_manager']))
            ->call('editBatch', $batch->id)
            ->set('edit_expiry_date', '')
            ->call('updateBatch')
            ->assertHasErrors('edit_expiry_date');
    }

    // ── who may do it ───────────────────────────────────────────────────

    public function test_a_pharmacist_cannot_correct_a_batch(): void
    {
        // They are barred from the catalogue; cost is not a clinical matter.
        $batch = $this->batch(cost: 85);

        $this->page($this->user(['pharmacist']))
            ->call('editBatch', $batch->id)
            ->set('edit_cost_price', '1')
            ->call('updateBatch');

        $this->assertEquals(85, $batch->fresh()->cost_price);
    }

    public function test_an_auditor_cannot_correct_a_batch(): void
    {
        $batch = $this->batch(cost: 85);

        $this->page($this->user(['auditor']))
            ->call('editBatch', $batch->id)
            ->set('edit_cost_price', '1')
            ->call('updateBatch');

        $this->assertEquals(85, $batch->fresh()->cost_price);
    }

    // ── bulk edit is gone ───────────────────────────────────────────────

    public function test_bulk_edit_no_longer_exists(): void
    {
        // Removed rather than hidden: it wrote stock movements with no user_id,
        // so a bulk stock change could not be traced to anybody.
        $component = file_get_contents(app_path('Livewire/Products/Index.php'));
        $view      = file_get_contents(resource_path('views/livewire/products/index.blade.php'));

        $this->assertStringNotContainsString('bulkEdit', $component);
        $this->assertStringNotContainsString('saveBulkEdits', $component);
        $this->assertStringNotContainsString('bulkEdit', $view);
        $this->assertStringNotContainsString('Bulk stock adjustment', $component);
    }

    public function test_the_products_page_still_works_without_it(): void
    {
        $this->batch();

        $this->page($this->user(['admin']))
            ->assertOk()
            ->assertSee('PARACETAMOL 500MG')
            ->assertDontSee('Bulk Edit');
    }

    public function test_a_corrected_cost_reprices_wholesale(): void
    {
        // Why this gap mattered: the wholesale price is derived from the
        // dearest batch in stock, so an uncorrectable typo would have
        // mispriced every wholesale sale of the drug.
        \App\Models\AppSetting::set('wholesale_markup_percent', 5);
        Product::forgetDefaultMarkup();

        $batch = $this->batch(cost: 8.5);   // meant 85
        $wholesaler = \App\Models\Customer::create([
            'name'  => 'MUSA',
            'type'  => 'wholesale',
            'phone' => '080' . random_int(10000000, 99999999),
        ]);

        $this->assertEquals(8.93, round($batch->product->fresh()->getPriceFor($wholesaler), 2));

        $this->page($this->user(['inventory_manager']))
            ->call('editBatch', $batch->id)
            ->set('edit_cost_price', '85')
            ->call('updateBatch');

        $this->assertEquals(89.25, round($batch->product->fresh()->getPriceFor($wholesaler), 2));
    }
}
