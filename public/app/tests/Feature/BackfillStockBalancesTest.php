<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recovering the before/after figures for movements recorded before the app
 * kept them.
 *
 * The money trail already holds them: Batch audits its quantity, so every
 * change carries an old and a new value. This reads that rather than replaying
 * the movement log, which would be a guess - batch quantities can also be
 * corrected directly, and those corrections are not movements.
 *
 * A movement is filled only when the batch, the timing and the size of the
 * change all agree. Anything short of that is left blank, because a plausible
 * number in a stock record is worse than an admitted gap.
 */
class BackfillStockBalancesTest extends TestCase
{
    use RefreshDatabase;

    private function batch(int $qty = 50): Batch
    {
        $product = Product::create([
            'name'          => 'PARACETAMOL ' . random_int(100, 999),
            'category_id'   => Category::firstOrCreate(['name' => 'MEDICINE'])->id,
            'selling_price' => 100,
            'reorder_level' => 1,
        ]);

        return Batch::create([
            'product_id'   => $product->id,
            'batch_number' => 'B' . random_int(1000, 9999),
            'expiry_date'  => now()->addYear(),
            'cost_price'   => 60,
            'quantity'     => $qty,
        ]);
    }

    /** A movement from before balances were kept. */
    private function oldMovement(Batch $batch, int $qty, string $type = 'sale'): StockMovement
    {
        $movement = StockMovement::create([
            'batch_id'  => $batch->id,
            'quantity'  => $qty,
            'type'      => $type,
            'reference' => 'OLD-' . random_int(100, 999),
            'user_id'   => User::factory()->create()->id,
        ]);

        // The hook fills this for anything recorded now; blank it to stand for
        // a row written before the column existed.
        $movement->forceFill(['balance_after' => null])->saveQuietly();

        return $movement->fresh();
    }

    /** The money-trail entry that movement would have written at the time. */
    private function auditEntry(Batch $batch, int $from, int $to, $at = null): AuditLog
    {
        $entry = AuditLog::create([
            'auditable_type'  => Batch::class,
            'auditable_id'    => (string) $batch->id,
            'auditable_label' => $batch->batch_number,
            'event'           => 'updated',
            'field'           => 'quantity',
            'old_value'       => (string) $from,
            'new_value'       => (string) $to,
        ]);

        // created_at is not fillable on AuditLog - Eloquent stamps it - so a
        // backdated fixture has to be forced after the fact.
        if ($at) {
            $entry->forceFill(['created_at' => $at])->saveQuietly();
        }

        return $entry->fresh();
    }

    public function test_it_recovers_the_balance_from_the_money_trail(): void
    {
        $batch    = $this->batch(54);
        $movement = $this->oldMovement($batch, 1, 'return');
        $this->auditEntry($batch, 53, 54);

        $this->artisan('stock:backfill-balances')->assertSuccessful();

        $this->assertSame(54, (int) $movement->fresh()->balance_after);
        $this->assertSame(53, $movement->fresh()->balanceBefore());
    }

    public function test_it_leaves_alone_what_it_cannot_match(): void
    {
        // Nothing in the trail for this one. A guessed figure in a stock record
        // is worse than an admitted gap.
        $movement = $this->oldMovement($this->batch(), -1);

        $this->artisan('stock:backfill-balances')->assertSuccessful();

        $this->assertNull($movement->fresh()->balance_after);
    }

    public function test_it_will_not_borrow_another_movement_s_entry(): void
    {
        // Two movements on the same batch seconds apart. Matching on batch and
        // time alone would let each take the other's figure; the size of the
        // change has to agree too.
        $batch = $this->batch(40);

        $sale   = $this->oldMovement($batch, -5);
        $return = $this->oldMovement($batch, 2, 'return');

        $this->auditEntry($batch, 45, 40);   // the sale
        $this->auditEntry($batch, 40, 42);   // the return

        $this->artisan('stock:backfill-balances')->assertSuccessful();

        $this->assertSame(40, (int) $sale->fresh()->balance_after);
        $this->assertSame(42, (int) $return->fresh()->balance_after);
    }

    public function test_an_entry_from_another_day_is_not_used(): void
    {
        $batch    = $this->batch();
        $movement = $this->oldMovement($batch, 1, 'return');

        $this->auditEntry($batch, 53, 54, now()->subDays(3));

        $this->artisan('stock:backfill-balances')->assertSuccessful();

        $this->assertNull($movement->fresh()->balance_after);
    }

    public function test_an_entry_for_another_batch_is_not_used(): void
    {
        $batch    = $this->batch();
        $movement = $this->oldMovement($batch, 1, 'return');

        $this->auditEntry($this->batch(), 53, 54);

        $this->artisan('stock:backfill-balances')->assertSuccessful();

        $this->assertNull($movement->fresh()->balance_after);
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $batch    = $this->batch(54);
        $movement = $this->oldMovement($batch, 1, 'return');
        $this->auditEntry($batch, 53, 54);

        $this->artisan('stock:backfill-balances', ['--dry-run' => true])->assertSuccessful();

        $this->assertNull($movement->fresh()->balance_after);
    }

    public function test_running_it_twice_is_safe(): void
    {
        $batch    = $this->batch(54);
        $movement = $this->oldMovement($batch, 1, 'return');
        $this->auditEntry($batch, 53, 54);

        $this->artisan('stock:backfill-balances')->assertSuccessful();
        $this->artisan('stock:backfill-balances')->assertSuccessful();

        $this->assertSame(54, (int) $movement->fresh()->balance_after);
    }

    public function test_it_does_not_disturb_a_balance_already_recorded(): void
    {
        $batch = $this->batch(40);

        // Written by the hook, as everything from now on is.
        $movement = StockMovement::create([
            'batch_id' => $batch->id, 'quantity' => -1, 'type' => 'sale',
            'reference' => 'NEW-1', 'user_id' => User::factory()->create()->id,
        ]);

        $this->auditEntry($batch, 999, 998);   // a wrong entry, deliberately

        $this->artisan('stock:backfill-balances')->assertSuccessful();

        $this->assertSame(40, (int) $movement->fresh()->balance_after);
    }
}
