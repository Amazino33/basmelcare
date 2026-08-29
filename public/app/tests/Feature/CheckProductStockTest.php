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
 * Reconciling one product against its own log.
 *
 * The command exists because a return was processed before running balances
 * were kept, and afterwards nobody could tell whether the stock had moved. It
 * has to give a firm answer where one is available and refuse to give one where
 * it is not - a confident "short by 1" that is really "I cannot tell" would
 * send somebody adjusting stock that was never wrong.
 */
class CheckProductStockTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $name = 'ARTEMETHER INJECTION'): Product
    {
        return Product::create([
            'name'          => $name,
            'category_id'   => Category::firstOrCreate(['name' => 'MEDICINE'])->id,
            'selling_price' => 1200,
            'reorder_level' => 1,
        ]);
    }

    private function batch(Product $product, int $qty): Batch
    {
        return Batch::create([
            'product_id'   => $product->id,
            'batch_number' => 'AUTO-' . random_int(10000, 99999),
            'expiry_date'  => now()->addYear(),
            'cost_price'   => 700,
            'quantity'     => $qty,
        ]);
    }

    private function movement(Batch $batch, int $qty, string $type, $at = null): StockMovement
    {
        $movement = StockMovement::create([
            'batch_id'  => $batch->id,
            'quantity'  => $qty,
            'type'      => $type,
            'reference' => strtoupper($type) . '-' . random_int(100, 999),
            'user_id'   => User::factory()->create()->id,
        ]);

        if ($at) {
            $movement->forceFill(['created_at' => $at])->saveQuietly();
        }

        return $movement->fresh();
    }

    private function auditQuantity(Batch $batch, int $from, int $to, $at = null): void
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

        if ($at) {
            $entry->forceFill(['created_at' => $at])->saveQuietly();
        }
    }

    // ── finding the product ─────────────────────────────────────────────

    public function test_it_finds_a_product_by_part_of_its_name(): void
    {
        $this->product();

        $this->artisan('stock:check', ['product' => 'ARTEMETHER'])
            ->expectsOutputToContain('ARTEMETHER INJECTION')
            ->assertSuccessful();
    }

    public function test_it_says_so_when_nothing_matches(): void
    {
        $this->artisan('stock:check', ['product' => 'NOTHING LIKE THIS'])
            ->expectsOutputToContain('No product matches')
            ->assertFailed();
    }

    public function test_it_refuses_a_search_that_is_too_broad(): void
    {
        // Dumping nine reports at somebody chasing one figure helps nobody.
        foreach (range(1, 9) as $i) {
            $this->product('INJECTION ' . $i);
        }

        $this->artisan('stock:check', ['product' => 'INJECTION'])
            ->expectsOutputToContain('Be more specific')
            ->assertFailed();
    }

    // ── the totals ──────────────────────────────────────────────────────

    public function test_it_totals_what_was_sold_and_returned(): void
    {
        $product = $this->product();
        $batch   = $this->batch($product, 54);

        $this->movement($batch, -1, 'sale');
        $this->movement($batch, -2, 'sale');
        $this->movement($batch, 1, 'return');

        $this->artisan('stock:check', ['product' => 'ARTEMETHER'])
            ->expectsOutputToContain('Sale')
            ->expectsOutputToContain('Return')
            ->assertSuccessful();
    }

    public function test_it_reports_the_total_across_batches(): void
    {
        $product = $this->product();
        $this->batch($product, 30);
        $this->batch($product, 24);

        $this->artisan('stock:check', ['product' => 'ARTEMETHER'])
            ->expectsOutputToContain('Total on hand across every batch: 54')
            ->assertSuccessful();
    }

    public function test_a_product_with_no_stock_is_reported_plainly(): void
    {
        $this->product();

        $this->artisan('stock:check', ['product' => 'ARTEMETHER'])
            ->expectsOutputToContain('Nothing has ever been stocked')
            ->assertSuccessful();
    }

    // ── the verdict ─────────────────────────────────────────────────────

    public function test_it_confirms_a_batch_that_adds_up(): void
    {
        $product = $this->product();
        $batch   = $this->batch($product, 54);

        // Held 55, sold one, returned one, sold one again: 54.
        $this->auditQuantity($batch, 55, 54, now()->subHours(5));
        $this->movement($batch, -1, 'sale',   now()->subHours(5));
        $this->movement($batch, 1,  'return', now()->subHours(4));
        $this->movement($batch, -1, 'sale',   now()->subHours(3));

        $this->artisan('stock:check', ['product' => 'ARTEMETHER'])
            ->expectsOutputToContain('Agrees')
            ->assertSuccessful();
    }

    public function test_it_catches_a_return_whose_stock_never_arrived(): void
    {
        // Exactly the reported situation: the +1 was logged as a movement and
        // the shelf never gained it.
        $product = $this->product();
        $batch   = $this->batch($product, 54);

        $this->auditQuantity($batch, 55, 54, now()->subHours(5));
        $this->movement($batch, -1, 'sale',   now()->subHours(5));
        $this->movement($batch, 1,  'return', now()->subHours(4));

        // One expectation, not two: both phrases sit on the same output line,
        // and each expectation consumes a line.
        $this->artisan('stock:check', ['product' => 'ARTEMETHER'])
            ->expectsOutputToContain('short by 1')
            ->assertSuccessful();
    }

    public function test_it_reports_a_surplus_as_a_surplus(): void
    {
        $product = $this->product();
        $batch   = $this->batch($product, 60);

        $this->auditQuantity($batch, 55, 54, now()->subHours(5));
        $this->movement($batch, -1, 'sale', now()->subHours(5));

        $this->artisan('stock:check', ['product' => 'ARTEMETHER'])
            ->expectsOutputToContain('a surplus of 6')
            ->assertSuccessful();
    }

    public function test_it_refuses_to_judge_a_batch_it_cannot_anchor(): void
    {
        // No money-trail entry means no trustworthy figure to count from. A
        // confident shortfall here would send somebody adjusting stock that was
        // never wrong.
        $product = $this->product();
        $batch   = $this->batch($product, 54);

        $this->movement($batch, -1, 'sale');

        $this->artisan('stock:check', ['product' => 'ARTEMETHER'])
            ->expectsOutputToContain('Cannot be checked')
            ->doesntExpectOutputToContain('Does not agree')
            ->assertSuccessful();
    }

    public function test_it_points_at_direct_batch_edits_before_blaming_the_count(): void
    {
        // A batch can be corrected directly, and that is not a movement - a
        // real reason for the two to differ that is not a missing unit.
        $product = $this->product();
        $batch   = $this->batch($product, 54);

        $this->auditQuantity($batch, 55, 54, now()->subHours(5));
        $this->movement($batch, 1, 'return', now()->subHours(4));

        $this->artisan('stock:check', ['product' => 'ARTEMETHER'])
            ->expectsOutputToContain('edited directly')
            ->assertSuccessful();
    }

    public function test_it_changes_nothing(): void
    {
        // Correcting stock is a person's decision, with a reason attached.
        $product = $this->product();
        $batch   = $this->batch($product, 54);

        $this->auditQuantity($batch, 55, 54, now()->subHours(5));
        $this->movement($batch, 1, 'return', now()->subHours(4));

        $this->artisan('stock:check', ['product' => 'ARTEMETHER'])->assertSuccessful();

        $this->assertSame(54, (int) $batch->fresh()->quantity);
        $this->assertSame(1, StockMovement::count());
    }

    public function test_it_can_be_limited_to_recent_movements(): void
    {
        $product = $this->product();
        $batch   = $this->batch($product, 54);

        $this->movement($batch, -20, 'sale', now()->subMonths(6));
        $this->movement($batch, -1,  'sale', now()->subDay());

        $this->artisan('stock:check', [
            'product' => 'ARTEMETHER',
            '--since' => now()->subWeek()->toDateString(),
        ])->expectsOutputToContain('-1')->assertSuccessful();
    }
}
