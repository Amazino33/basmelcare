<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Goods coming back have to reach the shelf.
 *
 * Reported from the counter: a returned drug did not reappear in stock. These
 * walk the shapes a real sale can take - sold by the pack, spread across two
 * batches, returned in part, returned twice - because the plain one-line case
 * was already covered and already worked.
 */
class ReturnRestocksTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => ['branch_manager'], 'status' => 'active']);
    }

    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name'          => 'PARACETAMOL ' . random_int(100, 999),
            'category_id'   => Category::firstOrCreate(['name' => 'MEDICINE'])->id,
            'selling_price' => 100,
            'reorder_level' => 1,
        ], $attributes));
    }

    private function batch(Product $product, int $qty, string $expiry = '+1 year'): Batch
    {
        return Batch::create([
            'product_id'   => $product->id,
            'batch_number' => 'B' . random_int(1000, 9999),
            'expiry_date'  => now()->modify($expiry),
            'cost_price'   => 60,
            'quantity'     => $qty,
        ]);
    }

    private function sale(): Sale
    {
        return Sale::create([
            'invoice_number' => 'INV-' . now()->format('Ymd') . '-' . random_int(1000, 9999),
            'user_id'        => $this->staff()->id,
            'total_amount'   => 0,
            'payment_method' => 'cash',
            'status'         => 'paid',
            'paid_at'        => now(),
        ]);
    }

    private function line(Sale $sale, Batch $batch, int $units, array $extra = []): SaleItem
    {
        return SaleItem::create(array_merge([
            'sale_id'    => $sale->id,
            'product_id' => $batch->product_id,
            'batch_id'   => $batch->id,
            'quantity'   => $units,
            'unit_price' => 100,
            'cost_price' => 60,
            'subtotal'   => $units * 100,
        ], $extra));
    }

    /** Process a return of the given units per sale item. */
    private function processReturn(Sale $sale, array $unitsByItemId): void
    {
        $page = Livewire::actingAs($this->staff())
            ->test(\App\Livewire\Sales\Index::class)
            ->call('openReturn', $sale->id);

        foreach ($unitsByItemId as $itemId => $units) {
            $page->set('returnQtys.' . $itemId, $units);
        }

        $page->call('processReturn');
    }

    // ── the shapes a sale can take ──────────────────────────────────────

    public function test_a_plain_line_goes_back_on_the_shelf(): void
    {
        $product = $this->product();
        $batch   = $this->batch($product, 40);   // 10 sold from 50
        $sale    = $this->sale();
        $item    = $this->line($sale, $batch, 10);

        $this->processReturn($sale, [$item->id => 10]);

        $this->assertSame(50, $batch->fresh()->quantity);
    }

    public function test_a_pack_sale_returns_every_unit_in_the_pack(): void
    {
        // quantity is stored in units even when the line was sold by the pack,
        // so two packs of ten is twenty. Returning it must put twenty back, not
        // two.
        $product = $this->product(['has_pack' => true, 'pack_size' => 10, 'pack_price' => 900]);
        $batch   = $this->batch($product, 30);   // 20 units (2 packs) sold from 50
        $sale    = $this->sale();
        $item    = $this->line($sale, $batch, 20, ['is_pack' => true, 'pack_size' => 10]);

        $this->processReturn($sale, [$item->id => 20]);

        $this->assertSame(50, $batch->fresh()->quantity);
    }

    public function test_a_sale_spread_over_two_batches_restocks_both(): void
    {
        // A quantity larger than one batch becomes two lines, each with its own
        // batch. Both have to come back, to the batch they left.
        $product = $this->product();
        $older   = $this->batch($product, 0,  '+2 months');   // 15 taken, emptied
        $newer   = $this->batch($product, 45, '+2 years');    // 5 taken from 50

        $sale = $this->sale();
        $a    = $this->line($sale, $older, 15);
        $b    = $this->line($sale, $newer, 5);

        $this->processReturn($sale, [$a->id => 15, $b->id => 5]);

        $this->assertSame(15, $older->fresh()->quantity, 'The emptied batch got nothing back.');
        $this->assertSame(50, $newer->fresh()->quantity);
    }

    public function test_returning_part_of_a_line_restocks_only_that_part(): void
    {
        $product = $this->product();
        $batch   = $this->batch($product, 40);
        $sale    = $this->sale();
        $item    = $this->line($sale, $batch, 10);

        $this->processReturn($sale, [$item->id => 4]);

        $this->assertSame(44, $batch->fresh()->quantity);
    }

    public function test_the_rest_can_come_back_later(): void
    {
        $product = $this->product();
        $batch   = $this->batch($product, 40);
        $sale    = $this->sale();
        $item    = $this->line($sale, $batch, 10);

        $this->processReturn($sale, [$item->id => 4]);
        $this->processReturn($sale, [$item->id => 6]);

        $this->assertSame(50, $batch->fresh()->quantity);
    }

    public function test_a_line_left_at_zero_is_not_restocked(): void
    {
        // Only what the operator actually typed comes back.
        $product = $this->product();
        $batch   = $this->batch($product, 40);
        $sale    = $this->sale();
        $keep    = $this->line($sale, $batch, 10);

        $other      = $this->product();
        $otherBatch = $this->batch($other, 20);
        $returned   = $this->line($sale, $otherBatch, 5);

        $this->processReturn($sale, [$keep->id => 0, $returned->id => 5]);

        $this->assertSame(40, $batch->fresh()->quantity, 'An untouched line was restocked.');
        $this->assertSame(25, $otherBatch->fresh()->quantity);
    }

    // ── the trail ───────────────────────────────────────────────────────

    public function test_the_movement_is_recorded_against_the_batch(): void
    {
        // Movement History is where anyone checks whether stock actually moved.
        $product = $this->product();
        $batch   = $this->batch($product, 40);
        $sale    = $this->sale();
        $item    = $this->line($sale, $batch, 10);

        $this->processReturn($sale, [$item->id => 10]);

        $movement = StockMovement::where('batch_id', $batch->id)->where('type', 'return')->sole();

        $this->assertSame(10, (int) $movement->quantity);
    }

    // ── why a line can always be restocked ──────────────────────────────

    public function test_a_sale_line_can_never_exist_without_a_batch(): void
    {
        // This is the guarantee the restocking rests on. If a line could lose
        // its batch, a return would pay the refund and put nothing back - the
        // exact fault reported from the counter, and one nothing would notice.
        $product = $this->product();
        $batch   = $this->batch($product, 40);
        $item    = $this->line($this->sale(), $batch, 10);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $item->forceFill(['batch_id' => null])->save();
    }

    public function test_a_failed_return_changes_nothing_at_all(): void
    {
        // Asking for more than was bought throws inside the transaction. The
        // stock must not be left half returned, and no refund may stand.
        $product = $this->product();
        $batch   = $this->batch($product, 40);
        $sale    = $this->sale();
        $item    = $this->line($sale, $batch, 10);

        $this->processReturn($sale, [$item->id => 999]);

        $this->assertSame(40, $batch->fresh()->quantity, 'Stock moved on a return that failed.');
        $this->assertSame(0, \App\Models\SaleReturn::count());
    }

    public function test_a_failed_return_says_why(): void
    {
        // "It did not work" with no reason is what made this hard to chase.
        $product = $this->product();
        $batch   = $this->batch($product, 40);
        $sale    = $this->sale();
        $item    = $this->line($sale, $batch, 10);

        $page = Livewire::actingAs($this->staff())
            ->test(\App\Livewire\Sales\Index::class)
            ->call('openReturn', $sale->id)
            ->set('returnQtys.' . $item->id, 999)
            ->call('processReturn');

        $this->assertStringContainsString('10', $page->get('returnError'),
            'The message did not say how many could still be returned.');
    }
}
