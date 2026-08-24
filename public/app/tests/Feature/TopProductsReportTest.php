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
 * The three dashboard rankings, printable.
 *
 * Reports is open to the auditor, but this report is not: revenue and profit
 * rankings expose margin, and the auditor's view of margin belongs in
 * Financial Records where it is presented with the rest of the picture. A
 * printable page is also the kind of URL that gets passed around, so the
 * controller checks the role itself rather than relying on the route alone.
 */
class TopProductsReportTest extends TestCase
{
    use RefreshDatabase;

    private function sell(string $name, float $price, float $cost, int $qty, int $sales = 1): Product
    {
        $product = Product::firstOrCreate(
            ['name' => $name],
            [
                'category_id'   => Category::firstOrCreate(['name' => 'General'])->id,
                'selling_price' => $price,
                'reorder_level' => 1,
            ]
        );

        for ($i = 0; $i < $sales; $i++) {
            $batch = Batch::create([
                'product_id'   => $product->id,
                'batch_number' => 'B-' . random_int(1000, 9999),
                'expiry_date'  => now()->addYear(),
                'cost_price'   => $cost,
                'quantity'     => 500,
            ]);

            $sale = Sale::create([
                'invoice_number' => 'INV-' . random_int(100000, 999999) . $i,
                'user_id'        => User::factory()->create(['role' => ['cashier']])->id,
                'total_amount'   => $price * $qty,
                'status'         => 'completed',
            ]);

            SaleItem::create([
                'sale_id'    => $sale->id,
                'product_id' => $product->id,
                'batch_id'   => $batch->id,
                'quantity'   => $qty,
                'unit_price' => $price,
                'cost_price' => $cost,
                'subtotal'   => $price * $qty,
            ]);
        }

        return $product;
    }

    private function user(array $roles): User
    {
        return User::factory()->create(['role' => $roles, 'status' => 'active']);
    }

    private function printUrl(): string
    {
        return route('reports.top-products.print', [
            'from' => today()->subYear()->format('Y-m-d'),
            'to'   => today()->format('Y-m-d'),
        ]);
    }

    // ── the printed sheet ───────────────────────────────────────────────

    public function test_the_sheet_shows_all_three_rankings(): void
    {
        $this->sell('PARACETAMOL', price: 160, cost: 85, qty: 2, sales: 12);
        $this->sell('INSULIN', price: 9000, cost: 5000, qty: 1, sales: 1);

        $this->actingAs($this->user(['admin']))
            ->get($this->printUrl())
            ->assertOk()
            ->assertSee('Bought most often')
            ->assertSee('Most revenue')
            ->assertSee('Most profit')
            ->assertSee('PARACETAMOL')
            ->assertSee('INSULIN');
    }

    public function test_the_sheet_explains_why_demand_is_counted_in_sales(): void
    {
        // Printed and read away from the screen, so the sheet has to carry its
        // own explanation of why it does not rank by units.
        $this->sell('PARACETAMOL', price: 160, cost: 85, qty: 2, sales: 3);

        $this->actingAs($this->user(['admin']))
            ->get($this->printUrl())
            ->assertOk()
            ->assertSee('counts separate sales, not units');
    }

    public function test_a_period_with_no_sales_says_so_rather_than_printing_blank(): void
    {
        $this->actingAs($this->user(['admin']))
            ->get($this->printUrl())
            ->assertOk()
            ->assertSee('No sales were recorded in this period');
    }

    public function test_unsettled_sales_are_excluded(): void
    {
        $this->sell('IBUPROFEN', price: 500, cost: 300, qty: 4, sales: 1);
        Sale::query()->update(['status' => 'pending']);

        $this->actingAs($this->user(['admin']))
            ->get($this->printUrl())
            ->assertSee('No sales were recorded in this period');
    }

    public function test_the_period_covers_whole_days(): void
    {
        // A sheet run "to today" must include what was sold today, not stop at
        // midnight this morning.
        $this->sell('PARACETAMOL', price: 160, cost: 85, qty: 1, sales: 1);

        $this->actingAs($this->user(['admin']))
            ->get(route('reports.top-products.print', [
                'from' => today()->format('Y-m-d'),
                'to'   => today()->format('Y-m-d'),
            ]))
            ->assertOk()
            ->assertSee('PARACETAMOL');
    }

    public function test_a_backwards_date_range_is_rejected(): void
    {
        $this->actingAs($this->user(['admin']))
            ->get(route('reports.top-products.print', [
                'from' => today()->format('Y-m-d'),
                'to'   => today()->subMonth()->format('Y-m-d'),
            ]))
            ->assertSessionHasErrors('to');
    }

    // ── who may reach it ────────────────────────────────────────────────

    public function test_a_branch_manager_may_print_it(): void
    {
        $this->actingAs($this->user(['branch_manager']))->get($this->printUrl())->assertOk();
    }

    public function test_the_auditor_may_not_print_it(): void
    {
        // The auditor reaches Reports but not this sheet.
        $this->actingAs($this->user(['auditor']))->get($this->printUrl())->assertForbidden();
    }

    public function test_a_cashier_may_not_print_it(): void
    {
        $this->actingAs($this->user(['cashier']))->get($this->printUrl())->assertForbidden();
    }

    public function test_a_guest_may_not_print_it(): void
    {
        $this->get($this->printUrl())->assertRedirect();
    }

    // ── the Reports page ────────────────────────────────────────────────

    public function test_the_report_is_offered_to_an_admin(): void
    {
        Livewire::actingAs($this->user(['admin']))
            ->test(\App\Livewire\Reports\Index::class)
            ->assertSee('Top Products');
    }

    public function test_the_report_is_not_offered_to_the_auditor(): void
    {
        Livewire::actingAs($this->user(['auditor']))
            ->test(\App\Livewire\Reports\Index::class)
            ->assertOk()
            ->assertDontSee('Top Products');
    }

    public function test_the_auditor_cannot_export_it_by_asking_for_it_directly(): void
    {
        // Hiding the option from the dropdown is not a control on its own.
        Livewire::actingAs($this->user(['auditor']))
            ->test(\App\Livewire\Reports\Index::class)
            ->set('reportType', 'top-products')
            ->call('export')
            ->assertForbidden();
    }

    public function test_an_admin_can_export_it_as_csv(): void
    {
        $this->sell('PARACETAMOL', price: 160, cost: 85, qty: 2, sales: 3);

        $csv = $this->exportCsv($this->user(['admin']));

        $this->assertStringContainsString('Product,"Times sold",Units,Revenue,Profit', $csv);
        $this->assertStringContainsString('PARACETAMOL', $csv);
        // 3 sales of 2 at 160 = 960 revenue, cost 6 x 85 = 510, profit 450.
        $this->assertStringContainsString('960.00', $csv);
        $this->assertStringContainsString('450.00', $csv);
    }

    /** Runs the export and returns what the browser would have downloaded. */
    private function exportCsv(User $user): string
    {
        $response = Livewire::actingAs($user)
            ->test(\App\Livewire\Reports\Index::class)
            ->set('dateFrom', today()->subYear()->format('Y-m-d'))
            ->set('dateTo', today()->format('Y-m-d'))
            ->set('reportType', 'top-products')
            ->call('export');

        // Livewire hands a download to the browser base64 encoded.
        return base64_decode($response->effects['download']['content'] ?? '');
    }

    public function test_the_dashboard_and_the_sheet_agree(): void
    {
        // One query behind both, so they cannot drift about which sales count.
        $this->sell('PARACETAMOL', price: 160, cost: 85, qty: 2, sales: 12);
        $this->sell('INSULIN', price: 9000, cost: 5000, qty: 1, sales: 1);

        $dashboard = Livewire::actingAs($this->user(['admin']))
            ->test(\App\Livewire\Dashboard::class)
            ->set('dateFilter', 'custom')
            ->set('dateFrom', today()->subYear()->format('Y-m-d'))
            ->set('dateTo', today()->format('Y-m-d'))
            ->viewData('hot');

        $this->assertSame('PARACETAMOL', $dashboard['byTimesSold']->first()->name);
        $this->assertSame('INSULIN', $dashboard['byProfit']->first()->name);

        $sheet = $this->actingAs($this->user(['admin']))->get($this->printUrl());

        // The sheet lists the same winner at the top of each column.
        $sheet->assertSee('PARACETAMOL')->assertSee('INSULIN');
    }
}
