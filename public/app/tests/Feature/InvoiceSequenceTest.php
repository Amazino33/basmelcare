<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every invoice in the period is listed, whatever its status.
 *
 * Listing only settled sales left holes in the numbering — a cancelled
 * INV-…-0001 and 0002 made the sequence appear to start at 0003. An
 * unexplained gap is indistinguishable from a deleted record, which is the
 * first thing an auditor should be able to rule out.
 *
 * Cancelled and unpaid invoices appear but contribute nothing to the totals.
 */
class InvoiceSequenceTest extends TestCase
{
    use RefreshDatabase;

    private function sale(string $invoice, string $status, float $price = 1000, float $cost = 600): Sale
    {
        $product = Product::create([
            'name' => 'Item ' . random_int(1000, 9999),
            'category_id' => Category::firstOrCreate(['name' => 'General'])->id,
            'selling_price' => $price, 'reorder_level' => 1,
        ]);

        $batch = Batch::create([
            'product_id' => $product->id, 'batch_number' => 'B-' . random_int(1000, 9999),
            'expiry_date' => now()->addYear(), 'cost_price' => $cost, 'quantity' => 10,
        ]);

        $sale = Sale::create([
            'invoice_number' => $invoice,
            'user_id'        => User::factory()->create(['role' => ['cashier']])->id,
            'total_amount'   => $price,
            'coupon_discount' => 0,
            'status'         => $status,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $product->id, 'batch_id' => $batch->id,
            'quantity' => 1, 'unit_price' => $price, 'cost_price' => $cost, 'subtotal' => $price,
        ]);

        return $sale;
    }

    private function auditor(): User
    {
        return User::factory()->create(['role' => ['auditor'], 'status' => 'active']);
    }

    private function page()
    {
        return Livewire::actingAs($this->auditor())->test(\App\Livewire\Finance\Index::class);
    }

    public function test_cancelled_invoices_appear_so_the_sequence_is_unbroken(): void
    {
        $this->sale('INV-20260820-0001-AAA', 'cancelled');
        $this->sale('INV-20260820-0002-BBB', 'cancelled');
        $this->sale('INV-20260820-0003-CCC', 'completed');

        $html = $this->page()->html();

        // All three numbers must be visible, not just the completed one.
        $this->assertStringContainsString('INV-20260820-0001-AAA', $html);
        $this->assertStringContainsString('INV-20260820-0002-BBB', $html);
        $this->assertStringContainsString('INV-20260820-0003-CCC', $html);
    }

    public function test_unpaid_invoices_appear_too(): void
    {
        $this->sale('INV-20260820-0001-AAA', 'pending');

        $this->assertStringContainsString('INV-20260820-0001-AAA', $this->page()->html());
    }

    public function test_cancelled_invoices_add_nothing_to_the_figures(): void
    {
        $this->sale('INV-20260820-0001-AAA', 'cancelled', price: 50000, cost: 30000);
        $this->sale('INV-20260820-0002-BBB', 'completed', price: 1000, cost: 600);

        $f = $this->page()->viewData('f');

        $this->assertEquals(1000, $f['revenue'], 'A cancelled invoice was counted as revenue.');
        $this->assertEquals(600, $f['cogs']);
        $this->assertEquals(400, $f['gross']);
        $this->assertSame(1, $f['saleCount'], 'Only settled sales count towards the total.');
    }

    public function test_unpaid_invoices_add_nothing_to_the_figures(): void
    {
        $this->sale('INV-20260820-0001-AAA', 'pending', price: 50000, cost: 30000);

        $f = $this->page()->viewData('f');

        $this->assertEquals(0, $f['revenue']);
        $this->assertEquals(0, $f['gross']);
    }

    public function test_the_counts_are_surfaced_for_the_auditor(): void
    {
        $this->sale('INV-1', 'cancelled');
        $this->sale('INV-2', 'cancelled');
        $this->sale('INV-3', 'pending');
        $this->sale('INV-4', 'completed');

        $f = $this->page()->viewData('f');

        $this->assertSame(2, $f['cancelledCount']);
        $this->assertSame(1, $f['pendingCount']);
        $this->assertSame(1, $f['saleCount']);
    }

    public function test_each_row_shows_why_it_does_or_does_not_count(): void
    {
        $this->sale('INV-CANCELLED', 'cancelled');
        $this->sale('INV-PENDING', 'pending');

        $html = $this->page()->html();

        $this->assertStringContainsString('cancelled — not counted', $html);
        $this->assertStringContainsString('not yet paid — not counted', $html);
    }

    public function test_a_settled_sale_still_shows_its_profit(): void
    {
        $this->sale('INV-DONE', 'completed', price: 1000, cost: 600);

        $html = $this->page()->html();

        $this->assertStringContainsString('INV-DONE', $html);
        $this->assertStringContainsString('Completed', $html);
        // 400 profit at 40% margin.
        $this->assertStringContainsString('40.0%', $html);
    }
}
