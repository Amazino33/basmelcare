<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Reproduces a real discrepancy: the system reported 10,800 cash while the
 * drawer held 9,600. The 1,200 gap was a 700 discount plus a 500 unpaid
 * balance, both counted as if they were money in hand.
 *
 * total_amount is the PRE-discount figure and includes amounts sold on credit,
 * so summing it and calling the result cash overstates the drawer twice over.
 */
class CashReconciliationTest extends TestCase
{
    use RefreshDatabase;

    /** The reported transaction: billed 10,800, 700 off, 9,600 paid, 500 owed. */
    private function theRealTransaction(): Sale
    {
        $product = Product::create([
            'name' => 'ASSORTED',
            'category_id' => Category::firstOrCreate(['name' => 'General'])->id,
            'selling_price' => 10800, 'reorder_level' => 1,
        ]);

        $batch = Batch::create([
            'product_id' => $product->id, 'batch_number' => 'B1',
            'expiry_date' => now()->addYear(), 'cost_price' => 6000, 'quantity' => 10,
        ]);

        $customer = Customer::create([
            'name' => 'Part Payer', 'type' => 'retail', 'phone' => '08031112233',
        ]);

        $sale = Sale::create([
            'invoice_number'  => 'INV-REAL-001',
            'user_id'         => User::factory()->create(['role' => ['cashier']])->id,
            'customer_id'     => $customer->id,
            'total_amount'    => 10800,   // pre-discount
            'coupon_discount' => 700,
            'status'          => 'paid',
            'paid_at'         => now(),
            'payment_details' => ['cash' => 9600, 'shortfall' => 500, 'coupon_discount' => 700],
        ]);

        SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $product->id, 'batch_id' => $batch->id,
            'quantity' => 1, 'unit_price' => 10800, 'cost_price' => 6000, 'subtotal' => 10800,
        ]);

        $debt = Debt::create([
            'sale_id' => $sale->id, 'customer_id' => $customer->id,
            'amount_owed' => 10100,   // billed less the discount
            'amount_paid' => 9600,
            'status' => 'partial',
        ]);

        DebtPayment::create([
            'debt_id' => $debt->id, 'amount' => 9600,
            'payment_method' => 'cash', 'at_point_of_sale' => true,
            'received_by' => User::factory()->create()->id,
        ]);

        return $sale;
    }

    private function dashboard()
    {
        return Livewire::actingAs(User::factory()->create(['role' => ['admin'], 'status' => 'active']))
            ->test(\App\Livewire\Dashboard::class);
    }

    public function test_the_drawer_figure_matches_the_cash_taken(): void
    {
        $this->theRealTransaction();

        $this->assertEquals(
            9600,
            $this->dashboard()->viewData('cashCollectedToday'),
            'Cash Collected does not match what the cashier actually holds.'
        );
    }

    public function test_the_billed_figure_excludes_the_discount(): void
    {
        $this->theRealTransaction();

        // 10,800 was never charged; the customer was billed 10,100.
        $this->assertEquals(10100, $this->dashboard()->viewData('totalSalesToday'));
    }

    public function test_billed_and_cash_differ_by_exactly_the_unpaid_balance(): void
    {
        $this->theRealTransaction();

        $c = $this->dashboard();

        $this->assertEquals(
            500,
            $c->viewData('totalSalesToday') - $c->viewData('cashCollectedToday'),
            'The gap between billed and cash should be the amount still owed.'
        );
    }

    public function test_the_sales_page_revenue_excludes_the_discount(): void
    {
        $this->theRealTransaction();

        $revenue = Livewire::actingAs(User::factory()->create(['role' => ['admin'], 'status' => 'active']))
            ->test(\App\Livewire\Sales\Index::class)
            ->viewData('totalRevenue');

        $this->assertEquals(10100, $revenue, 'Sales revenue still includes the discount.');
    }

    public function test_settling_the_balance_moves_it_into_cash(): void
    {
        $sale = $this->theRealTransaction();
        $debt = Debt::where('sale_id', $sale->id)->firstOrFail();

        DebtPayment::create([
            'debt_id' => $debt->id, 'amount' => 500,
            'payment_method' => 'cash', 'at_point_of_sale' => false,
            'received_by' => User::factory()->create()->id,
        ]);

        $c = $this->dashboard();

        $this->assertEquals(10100, $c->viewData('cashCollectedToday'));
        // Once settled, billed and cash agree.
        $this->assertEquals($c->viewData('totalSalesToday'), $c->viewData('cashCollectedToday'));
    }

    public function test_the_dashboard_labels_the_two_figures_distinctly(): void
    {
        $this->theRealTransaction();

        $html = $this->dashboard()->html();

        $this->assertStringContainsString('Cash Collected', $html);
        $this->assertStringContainsString('in the drawer', $html);
        $this->assertStringContainsString('billed', $html);
    }

    public function test_a_fully_paid_sale_shows_no_gap(): void
    {
        Sale::create([
            'invoice_number' => 'INV-CLEAN-1',
            'user_id' => User::factory()->create(['role' => ['cashier']])->id,
            'total_amount' => 5000,
            'coupon_discount' => 0,
            'status' => 'completed',
            'payment_details' => ['cash' => 5000],
        ]);

        $c = $this->dashboard();

        $this->assertEquals(5000, $c->viewData('totalSalesToday'));
        $this->assertEquals(5000, $c->viewData('cashCollectedToday'));
    }
    public function test_sales_history_cash_matches_the_drawer(): void
    {
        $this->theRealTransaction();

        $c = Livewire::actingAs(User::factory()->create(['role' => ['admin'], 'status' => 'active']))
            ->test(\App\Livewire\Sales\Index::class);

        // The reported problem: this screen showed the billed figure as cash.
        $this->assertEquals(9600, $c->viewData('cashCollected'),
            'Sales History cash does not match the drawer.');
        $this->assertEquals(9600, $c->viewData('collected')['cash']);
    }

    public function test_sales_history_money_taken_excludes_the_discount_and_debt(): void
    {
        $this->theRealTransaction();

        $c = Livewire::actingAs(User::factory()->create(['role' => ['admin'], 'status' => 'active']))
            ->test(\App\Livewire\Sales\Index::class);

        // Billed 10,100 but only 9,600 tendered - the 500 is owed, not held.
        $this->assertEquals(10100, $c->viewData('totalRevenue'));
        $this->assertEquals(500, $c->viewData('totalRevenue') - $c->viewData('cashCollected'));
    }

    public function test_sales_history_counts_a_later_repayment_as_cash(): void
    {
        $sale = $this->theRealTransaction();
        $debt = Debt::where('sale_id', $sale->id)->firstOrFail();

        DebtPayment::create([
            'debt_id' => $debt->id, 'amount' => 500,
            'payment_method' => 'cash', 'at_point_of_sale' => false,
            'received_by' => User::factory()->create()->id,
        ]);

        $c = Livewire::actingAs(User::factory()->create(['role' => ['admin'], 'status' => 'active']))
            ->test(\App\Livewire\Sales\Index::class);

        $this->assertEquals(10100, $c->viewData('cashCollected'));
    }
    public function test_a_repayment_by_transfer_is_not_counted_as_cash(): void
    {
        $sale = $this->theRealTransaction();
        $debt = Debt::where('sale_id', $sale->id)->firstOrFail();

        DebtPayment::create([
            'debt_id' => $debt->id, 'amount' => 500,
            'payment_method' => 'transfer', 'at_point_of_sale' => false,
            'received_by' => User::factory()->create()->id,
        ]);

        $c = Livewire::actingAs(User::factory()->create(['role' => ['admin'], 'status' => 'active']))
            ->test(\App\Livewire\Sales\Index::class);

        $collected = $c->viewData('collected');

        $this->assertEquals(9600, $collected['cash'], 'A transfer repayment was counted as cash.');
        $this->assertEquals(500, $collected['transfer']);
        $this->assertEquals(10100, $c->viewData('cashCollected'));
    }

    public function test_change_given_comes_off_the_cash_line(): void
    {
        Sale::create([
            'invoice_number' => 'INV-CHANGE-1',
            'user_id' => User::factory()->create(['role' => ['cashier']])->id,
            'total_amount' => 4500,
            'coupon_discount' => 0,
            'status' => 'completed',
            // Customer handed over 5,000 and took 500 back.
            'payment_details' => ['cash' => 5000, 'change_given' => 500],
        ]);

        $c = Livewire::actingAs(User::factory()->create(['role' => ['admin'], 'status' => 'active']))
            ->test(\App\Livewire\Sales\Index::class);

        $this->assertEquals(4500, $c->viewData('collected')['cash']);
        $this->assertEquals(4500, $c->viewData('cashCollected'));
    }

    public function test_the_method_lines_add_up_to_cash_collected(): void
    {
        $this->theRealTransaction();

        Sale::create([
            'invoice_number' => 'INV-SPLIT-1',
            'user_id' => User::factory()->create(['role' => ['cashier']])->id,
            'total_amount' => 45000,
            'coupon_discount' => 0,
            'status' => 'completed',
            'payment_details' => ['cash' => 22500, 'transfer' => 22500],
        ]);

        $c = Livewire::actingAs(User::factory()->create(['role' => ['admin'], 'status' => 'active']))
            ->test(\App\Livewire\Sales\Index::class);

        $collected = $c->viewData('collected');

        // What the panel lists must equal the headline figure, or the two
        // disagree on screen and neither can be trusted.
        $this->assertEquals(
            array_sum($collected),
            $c->viewData('cashCollected'),
            'The per-method lines do not add up to Cash Collected.'
        );
    }

    public function test_a_split_payment_is_shared_between_methods(): void
    {
        Sale::create([
            'invoice_number' => 'INV-SPLIT-2',
            'user_id' => User::factory()->create(['role' => ['cashier']])->id,
            'total_amount' => 45000,
            'coupon_discount' => 0,
            'status' => 'completed',
            'payment_details' => ['cash' => 22500, 'transfer' => 22500],
        ]);

        $collected = Livewire::actingAs(User::factory()->create(['role' => ['admin'], 'status' => 'active']))
            ->test(\App\Livewire\Sales\Index::class)
            ->viewData('collected');

        $this->assertEquals(22500, $collected['cash']);
        $this->assertEquals(22500, $collected['transfer']);
    }
    public function test_an_unrecorded_sale_reports_money_taken_not_billed(): void
    {
        $customer = Customer::create([
            'name' => 'Old Debtor', 'type' => 'retail', 'phone' => '08039998888',
        ]);

        // An older sale with no payment breakdown, part of it still owed.
        $sale = Sale::create([
            'invoice_number'  => 'INV-OLD-1',
            'user_id'         => User::factory()->create(['role' => ['cashier']])->id,
            'customer_id'     => $customer->id,
            'total_amount'    => 5000,
            'coupon_discount' => 0,
            'status'          => 'completed',
            'payment_details' => null,
        ]);

        Debt::create([
            'sale_id' => $sale->id, 'customer_id' => $customer->id,
            'amount_owed' => 5000, 'amount_paid' => 3000, 'status' => 'partial',
        ]);

        $c = Livewire::actingAs(User::factory()->create(['role' => ['admin'], 'status' => 'active']))
            ->test(\App\Livewire\Sales\Index::class);

        // 3,000 was taken; the other 2,000 is still owed and was never money.
        $this->assertEquals(3000, $c->viewData('unrecordedCash'),
            'Unrecorded money still reports the billed amount.');
    }

    public function test_a_fully_unpaid_unrecorded_sale_contributes_nothing(): void
    {
        $customer = Customer::create([
            'name' => 'Owes Everything', 'type' => 'retail', 'phone' => '08037776666',
        ]);

        $sale = Sale::create([
            'invoice_number'  => 'INV-OLD-2',
            'user_id'         => User::factory()->create(['role' => ['cashier']])->id,
            'customer_id'     => $customer->id,
            'total_amount'    => 5000,
            'coupon_discount' => 0,
            'status'          => 'completed',
            'payment_details' => null,
        ]);

        Debt::create([
            'sale_id' => $sale->id, 'customer_id' => $customer->id,
            'amount_owed' => 5000, 'amount_paid' => 0, 'status' => 'unpaid',
        ]);

        $c = Livewire::actingAs(User::factory()->create(['role' => ['admin'], 'status' => 'active']))
            ->test(\App\Livewire\Sales\Index::class);

        $this->assertEquals(0, $c->viewData('unrecordedCash'));
        $this->assertEquals(0, $c->viewData('cashCollected'));
    }
}