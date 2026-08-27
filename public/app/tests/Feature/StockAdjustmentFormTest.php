<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The stock adjustment form reveals itself in steps: choose a product, then a
 * batch, then how much and why.
 *
 * The batch select was bound with a plain wire:model, which does not reach the
 * server until the next request - so choosing a batch changed nothing, the
 * fields gated on $batch_id never appeared, and the page looked broken with
 * nothing failing.
 *
 * A component test cannot catch that on its own: ->set() always syncs, so the
 * flow passes either way. The binding itself has to be asserted.
 */
class StockAdjustmentFormTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['role' => ['inventory_manager'], 'status' => 'active']);
    }

    private function batch(int $qty = 100): Batch
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
            'expiry_date'  => now()->addYear(),
            'cost_price'   => 85,
            'quantity'     => $qty,
        ]);
    }

    private function page(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::actingAs($this->user())->test(\App\Livewire\Stock\Adjustments::class);
    }

    // ── the binding that broke it ───────────────────────────────────────

    public function test_choosing_a_batch_reaches_the_server(): void
    {
        // Everything below the batch select is gated on $batch_id. Without
        // .live the selection never arrives and the form stops dead.
        $view = file_get_contents(resource_path('views/livewire/stock/adjustments.blade.php'));

        $this->assertStringContainsString('wire:model.live="batch_id"', $view);
        $this->assertStringNotContainsString('wire:model="batch_id"', $view);
    }

    public function test_choosing_a_product_reaches_the_server(): void
    {
        $view = file_get_contents(resource_path('views/livewire/stock/adjustments.blade.php'));

        $this->assertStringContainsString('wire:model.live="product_id"', $view);
    }

    public function test_the_product_picker_is_searchable(): void
    {
        // A plain select of several hundred drugs is unusable at a counter.
        $view = file_get_contents(resource_path('views/livewire/stock/adjustments.blade.php'));

        $this->assertStringContainsString('x-choices-offline', $view);
        $this->assertStringContainsString('searchable', $view);
        $this->assertStringContainsString('wire:model.live="product_id"', $view);
    }

    public function test_every_product_is_offered_to_search_through(): void
    {
        foreach (['PARACETAMOL 500MG', 'AMOXIL 500MG', 'ARBITEL 80H'] as $name) {
            Product::create([
                'name'          => $name,
                'category_id'   => Category::firstOrCreate(['name' => 'General'])->id,
                'selling_price' => 200,
                'reorder_level' => 1,
            ]);
        }

        $this->assertCount(3, $this->page()->viewData('products'));
    }

    // ── the form fills in as you go ─────────────────────────────────────

    public function test_the_batch_list_appears_once_a_product_is_chosen(): void
    {
        $batch = $this->batch();

        $page = $this->page()->call('openAdjustment')->set('product_id', $batch->product_id);

        $this->assertCount(1, $page->viewData('batches'));
        $page->assertSee('B-2291');
    }

    public function test_the_batch_list_shows_what_is_on_hand(): void
    {
        // So the person adjusting can see what they are adjusting from.
        $batch = $this->batch(qty: 137);

        $this->page()->call('openAdjustment')
            ->set('product_id', $batch->product_id)
            ->assertSee('137 units');
    }

    public function test_no_batches_are_offered_before_a_product_is_chosen(): void
    {
        $this->batch();

        $this->assertCount(0, $this->page()->viewData('batches'));
    }

    // ── the adjustment itself ───────────────────────────────────────────

    public function test_adding_stock_raises_the_batch(): void
    {
        $batch = $this->batch(qty: 100);

        $this->page()->call('openAdjustment')
            ->set('product_id', $batch->product_id)
            ->set('batch_id', $batch->id)
            ->set('adjustment_type', 'add')
            ->set('reason', 'Stock count correction')
            ->set('adjust_qty', 20)
            ->call('adjust');

        $this->assertSame(120, (int) $batch->fresh()->quantity);
    }

    public function test_removing_stock_lowers_the_batch(): void
    {
        $batch = $this->batch(qty: 100);

        $this->page()->call('openAdjustment')
            ->set('product_id', $batch->product_id)
            ->set('batch_id', $batch->id)
            ->set('adjustment_type', 'remove')
            ->set('reason', 'Damaged goods')
            ->set('adjust_qty', 30)
            ->call('adjust');

        $this->assertSame(70, (int) $batch->fresh()->quantity);
    }

    public function test_it_will_not_remove_more_than_is_there(): void
    {
        $batch = $this->batch(qty: 10);

        $this->page()->call('openAdjustment')
            ->set('product_id', $batch->product_id)
            ->set('batch_id', $batch->id)
            ->set('adjustment_type', 'remove')
            ->set('reason', 'Damaged goods')
            ->set('adjust_qty', 50)
            ->call('adjust');

        $this->assertSame(10, (int) $batch->fresh()->quantity);
    }

    public function test_a_reason_is_required(): void
    {
        // An adjustment without one is an unexplained change to stock levels.
        $batch = $this->batch();

        $this->page()->call('openAdjustment')
            ->set('product_id', $batch->product_id)
            ->set('batch_id', $batch->id)
            ->set('adjustment_type', 'remove')
            ->set('reason', '')
            ->set('adjust_qty', 5)
            ->call('adjust')
            ->assertHasErrors('reason');
    }

    public function test_the_adjustment_is_recorded_against_whoever_made_it(): void
    {
        $batch = $this->batch();
        $user  = $this->user();

        Livewire::actingAs($user)
            ->test(\App\Livewire\Stock\Adjustments::class)
            ->call('openAdjustment')
            ->set('product_id', $batch->product_id)
            ->set('batch_id', $batch->id)
            ->set('adjustment_type', 'add')
            ->set('reason', 'Stock count correction')
            ->set('adjust_qty', 5)
            ->set('adjust_note', 'Miscounted on intake')
            ->call('adjust');

        $this->assertDatabaseHas('stock_movements', [
            'batch_id' => $batch->id,
            'type'     => 'adjustment',
            'user_id'  => $user->id,
            'note'     => 'Miscounted on intake',
        ]);
    }

    public function test_a_removal_is_recorded_as_a_negative_movement(): void
    {
        $batch = $this->batch(qty: 100);

        $this->page()->call('openAdjustment')
            ->set('product_id', $batch->product_id)
            ->set('batch_id', $batch->id)
            ->set('adjustment_type', 'remove')
            ->set('reason', 'Theft/loss')
            ->set('adjust_qty', 8)
            ->call('adjust');

        $this->assertSame(-8, (int) StockMovement::where('type', 'adjustment')->value('quantity'));
    }
}
