<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Search narrows the invoice list AND the figures above it, so a filtered
 * total always describes exactly the invoices on screen. If those two could
 * diverge, a filtered total would be indistinguishable from a period total -
 * which is the one mistake an auditor must never be led into.
 */
class FinanceSearchTest extends TestCase
{
    use RefreshDatabase;

    private function auditor(): User
    {
        return User::factory()->create(['role' => ['auditor'], 'status' => 'active']);
    }

    private function sell(
        string $invoice,
        float $price,
        float $cost,
        ?string $customer = null,
        ?string $seller = null,
        string $product = 'PARACETAMOL 500MG',
        string $status = 'completed',
    ): Sale {
        $p = Product::firstOrCreate(
            ['name' => $product],
            [
                'category_id' => Category::firstOrCreate(['name' => 'General'])->id,
                'selling_price' => $price, 'reorder_level' => 1,
            ]
        );

        $batch = Batch::create([
            'product_id' => $p->id, 'batch_number' => 'B-' . random_int(1000, 9999),
            'expiry_date' => now()->addYear(), 'cost_price' => $cost, 'quantity' => 50,
        ]);

        $sale = Sale::create([
            'invoice_number' => $invoice,
            'user_id' => User::factory()->create([
                'name' => $seller ?? 'Idara Sales', 'role' => ['sales'],
            ])->id,
            'customer_id' => $customer
                ? Customer::create([
                    'name' => $customer, 'type' => 'retail',
                    'phone' => '0803' . random_int(1000000, 9999999),
                ])->id
                : null,
            'total_amount' => $price,
            'coupon_discount' => 0,
            'status' => $status,
            'payment_details' => ['cash' => $price],
        ]);

        SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $p->id, 'batch_id' => $batch->id,
            'quantity' => 1, 'unit_price' => $price, 'cost_price' => $cost, 'subtotal' => $price,
        ]);

        return $sale;
    }

    private function page(array $state = [])
    {
        $c = Livewire::actingAs($this->auditor())->test(\App\Livewire\Finance\Index::class);

        foreach ($state as $k => $v) {
            $c->set($k, $v);
        }

        return $c;
    }

    // -- Finding things ------------------------------------------------

    public function test_search_by_invoice_number(): void
    {
        $this->sell('INV-AAA-111', 1000, 600);
        $this->sell('INV-BBB-222', 2000, 900);

        $html = $this->page(['search' => 'BBB'])->html();

        $this->assertStringContainsString('INV-BBB-222', $html);
        $this->assertStringNotContainsString('INV-AAA-111', $html);
    }

    public function test_search_by_customer_name(): void
    {
        $this->sell('INV-AAA-111', 1000, 600, customer: 'Aisha Bello');
        $this->sell('INV-BBB-222', 2000, 900, customer: 'Musa Danjuma');

        $html = $this->page(['search' => 'aisha'])->html();

        $this->assertStringContainsString('INV-AAA-111', $html);
        $this->assertStringNotContainsString('INV-BBB-222', $html);
    }

    public function test_search_by_staff_name(): void
    {
        $this->sell('INV-AAA-111', 1000, 600, seller: 'Bola Cashier');
        $this->sell('INV-BBB-222', 2000, 900, seller: 'Idara Sales');

        $html = $this->page(['search' => 'Bola'])->html();

        $this->assertStringContainsString('INV-AAA-111', $html);
        $this->assertStringNotContainsString('INV-BBB-222', $html);
    }

    public function test_search_by_product_name(): void
    {
        $this->sell('INV-AAA-111', 1000, 600, product: 'AMOXICILLIN 500MG');
        $this->sell('INV-BBB-222', 2000, 900, product: 'IBUPROFEN 400MG');

        $html = $this->page(['search' => 'AMOXI'])->html();

        $this->assertStringContainsString('INV-AAA-111', $html);
        $this->assertStringNotContainsString('INV-BBB-222', $html);
    }

    // -- The figures follow the search ---------------------------------

    public function test_totals_describe_only_the_matching_invoices(): void
    {
        $this->sell('INV-AAA-111', 1000, 600, seller: 'Bola Cashier');
        $this->sell('INV-BBB-222', 5000, 3000, seller: 'Idara Sales');

        $f = $this->page(['search' => 'Bola'])->viewData('f');

        $this->assertEquals(1000, $f['revenue'], 'Revenue did not follow the search.');
        $this->assertEquals(600, $f['cogs'], 'Cost of goods did not follow the search.');
        $this->assertEquals(400, $f['gross']);
        $this->assertSame(1, $f['saleCount']);
    }

    public function test_the_method_breakdown_follows_the_search(): void
    {
        $this->sell('INV-AAA-111', 1000, 600, seller: 'Bola Cashier');
        $this->sell('INV-BBB-222', 5000, 3000, seller: 'Idara Sales');

        $m = $this->page(['search' => 'Bola'])->viewData('f')['methods'];

        $this->assertEquals(1000, $m['byMethod']['cash']);
        $this->assertEquals(1000, $m['settledTotal']);
    }

    public function test_the_breakdown_still_reconciles_when_filtered(): void
    {
        $this->sell('INV-AAA-111', 1000, 600, seller: 'Bola Cashier');
        $this->sell('INV-BBB-222', 5000, 3000, seller: 'Idara Sales');

        $m = $this->page(['search' => 'Bola'])->viewData('f')['methods'];

        $this->assertEquals(
            $m['settledTotal'],
            $m['methodTotal'] + $m['unrecorded'],
            'Filtered breakdown does not add up.'
        );
    }

    public function test_no_match_yields_zero_rather_than_the_period_total(): void
    {
        $this->sell('INV-AAA-111', 1000, 600);

        $f = $this->page(['search' => 'nothing-matches-this'])->viewData('f');

        $this->assertEquals(0, $f['revenue']);
        $this->assertEquals(0, $f['gross']);
        $this->assertSame(0, $f['saleCount']);
    }

    // -- Status filter -------------------------------------------------

    public function test_cancelled_only_shows_cancelled_and_earns_nothing(): void
    {
        $this->sell('INV-DONE-1', 1000, 600);
        $this->sell('INV-VOID-1', 4000, 2000, status: 'cancelled');

        $c = $this->page(['statusFilter' => 'cancelled']);
        $html = $c->html();

        $this->assertStringContainsString('INV-VOID-1', $html);
        $this->assertStringNotContainsString('INV-DONE-1', $html);
        // Cancelled invoices never contribute, filtered or not.
        $this->assertEquals(0, $c->viewData('f')['revenue']);
    }

    public function test_settled_only_hides_cancelled(): void
    {
        $this->sell('INV-DONE-1', 1000, 600);
        $this->sell('INV-VOID-1', 4000, 2000, status: 'cancelled');

        $html = $this->page(['statusFilter' => 'settled'])->html();

        $this->assertStringContainsString('INV-DONE-1', $html);
        $this->assertStringNotContainsString('INV-VOID-1', $html);
    }

    // -- Making the filter obvious -------------------------------------

    public function test_a_filtered_view_says_so_prominently(): void
    {
        $this->sell('INV-AAA-111', 1000, 600, seller: 'Bola Cashier');
        $this->sell('INV-BBB-222', 5000, 3000, seller: 'Idara Sales');

        $html = $this->page(['search' => 'Bola'])->html();

        $this->assertStringContainsString('Filtered view', $html);
        $this->assertStringContainsString('not the whole period', $html);
    }

    public function test_cash_movement_is_withheld_while_filtered(): void
    {
        $this->sell('INV-AAA-111', 1000, 600);

        // Expenses and stock purchases cannot be attributed to a sale search,
        // so showing them beside filtered revenue would be incoherent.
        $html = $this->page(['search' => 'INV-AAA'])->html();

        $this->assertStringContainsString('cannot be tied to a search', $html);
        $this->assertStringNotContainsString('Net cash movement', $html);
    }

    public function test_unfiltered_view_shows_no_banner_and_keeps_cash(): void
    {
        $this->sell('INV-AAA-111', 1000, 600);

        $html = $this->page()->html();

        $this->assertStringNotContainsString('Filtered view', $html);
        $this->assertStringContainsString('Net cash movement', $html);
    }

    public function test_clearing_restores_the_full_period(): void
    {
        $this->sell('INV-AAA-111', 1000, 600, seller: 'Bola Cashier');
        $this->sell('INV-BBB-222', 5000, 3000, seller: 'Idara Sales');

        $c = $this->page(['search' => 'Bola', 'statusFilter' => 'settled'])
            ->call('clearFilters');

        $c->assertSet('search', '')->assertSet('statusFilter', 'all');
        $this->assertEquals(6000, $c->viewData('f')['revenue']);
    }
}
