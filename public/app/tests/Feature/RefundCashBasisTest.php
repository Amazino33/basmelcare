<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CreditPayout;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A refund only empties the drawer if it was paid in cash.
 *
 * Store credit costs nothing at the moment it is given - it creates something
 * owed, and the drawer is lighter later, when the customer draws it. That
 * payout is already counted. Subtracting the refund as well counted the same
 * money twice, and every refund was store credit, so it was always wrong.
 *
 * Profit is a different question and nets every refund regardless: the sale
 * was undone whichever way the customer was paid.
 */
class RefundCashBasisTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => ['branch_manager'], 'status' => 'active']);
    }

    private function customer(): Customer
    {
        return Customer::create([
            'name' => 'ADAEZE OKON', 'type' => 'retail',
            'phone' => '080' . random_int(10000000, 99999999),
        ]);
    }

    /** A ₦10,000 cash sale of one unit. */
    private function sale(?Customer $customer): Sale
    {
        $product = Product::create([
            'name'          => 'PRODUCT ' . random_int(1000, 9999),
            'category_id'   => Category::firstOrCreate(['name' => 'MEDICINE'])->id,
            'selling_price' => 10000,
            'reorder_level' => 1,
        ]);

        $batch = Batch::create([
            'product_id' => $product->id, 'batch_number' => 'B' . random_int(100, 999),
            'expiry_date' => now()->addYear(), 'cost_price' => 6000, 'quantity' => 20,
        ]);

        $sale = Sale::create([
            'invoice_number' => 'INV-' . now()->format('Ymd') . '-' . random_int(1000, 9999),
            'user_id'        => $this->staff()->id,
            'customer_id'    => $customer?->id,
            'total_amount'   => 10000,
            'payment_method' => 'cash',
            'status'         => 'paid',
            'paid_at'        => now(),
            'payment_details' => ['cash' => 10000],
        ]);

        SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $product->id, 'batch_id' => $batch->id,
            'quantity' => 1, 'unit_price' => 10000, 'cost_price' => 6000, 'subtotal' => 10000,
        ]);

        return $sale->fresh(['saleItems']);
    }

    private function refund(Sale $sale, string $method): void
    {
        Livewire::actingAs($this->staff())
            ->test(\App\Livewire\Sales\Index::class)
            ->call('openReturn', $sale->id)
            ->set('refundMethod', $method)
            ->set('returnQtys.' . $sale->saleItems->first()->id, 1)
            ->call('processReturn');
    }

    private function figures(): array
    {
        return Livewire::actingAs($this->staff())
            ->test(\App\Livewire\Finance\Index::class)
            ->viewData('f');
    }

    // ── the drawer ──────────────────────────────────────────────────────

    public function test_a_cash_refund_takes_money_out_of_the_takings(): void
    {
        $this->refund($this->sale(null), SaleReturn::CASH);

        $this->assertEquals(0, $this->figures()['collected'],
            '₦10,000 came in and ₦10,000 went back out.');
    }

    public function test_a_credit_refund_does_not(): void
    {
        // The cash is still in the drawer. What changed is that the pharmacy
        // now owes it.
        $this->refund($this->sale($this->customer()), SaleReturn::CREDIT);

        $this->assertEquals(10000, $this->figures()['collected'],
            'A store-credit refund emptied the drawer on paper while the money sat in it.');
    }

    public function test_the_credit_leaves_the_drawer_when_it_is_drawn_and_only_then(): void
    {
        // The double count in one test: the refund and the payout are the same
        // ₦10,000, and only the payout is cash.
        $customer = $this->customer();
        $this->refund($this->sale($customer), SaleReturn::CREDIT);

        CreditPayout::create([
            'customer_id'    => $customer->id,
            'amount'         => 10000,
            'balance_before' => 10000,
            'balance_after'  => 0,
            'cashier_id'     => $this->staff()->id,
        ]);
        $customer->update(['credit_balance' => 0]);

        $this->assertEquals(0, $this->figures()['collected']);
    }

    // ── profit is a different question ──────────────────────────────────

    public function test_profit_nets_a_refund_whichever_way_it_was_paid(): void
    {
        // The sale was undone. How the customer was reimbursed does not change
        // that, so revenue drops either way.
        $this->refund($this->sale(null), SaleReturn::CASH);
        $cash = $this->figures()['netRevenue'];

        $this->refund($this->sale($this->customer()), SaleReturn::CREDIT);
        $both = $this->figures()['netRevenue'];

        $this->assertEquals(0, $cash);
        $this->assertEquals(0, $both, 'A credit refund left revenue standing.');
    }

    public function test_returned_stock_stops_being_a_cost(): void
    {
        $this->refund($this->sale(null), SaleReturn::CASH);

        $this->assertEquals(0, $this->figures()['cogs'],
            'Goods back on the shelf were still counted as sold.');
    }

    // ── the figures stay separable ──────────────────────────────────────

    public function test_cash_refunds_are_reported_apart_from_all_refunds(): void
    {
        $this->refund($this->sale(null), SaleReturn::CASH);
        $this->refund($this->sale($this->customer()), SaleReturn::CREDIT);

        $f = $this->figures();

        $this->assertEquals(20000, $f['refunds'], 'Both refunds belong in the trading figure.');
        $this->assertEquals(10000, $f['cashRefunds'], 'Only one of them emptied the drawer.');
    }

    // ── the method breakdown ────────────────────────────────────────────

    private function salesHistory(): array
    {
        $page = Livewire::actingAs($this->staff())
            ->test(\App\Livewire\Sales\Index::class);

        return [
            'collected'     => $page->viewData('collected'),
            'cashCollected' => $page->viewData('cashCollected'),
            'cashRefunded'  => $page->viewData('cashRefunded'),
        ];
    }

    private function financeMethods(): array
    {
        return Livewire::actingAs($this->staff())
            ->test(\App\Livewire\Finance\Index::class)
            ->viewData('f')['methods'];
    }

    public function test_a_cash_refund_comes_off_the_cash_line(): void
    {
        // Money handed back left the drawer exactly as change does, so the cash
        // figure has to fall - not merely report the refund beside it.
        $this->refund($this->sale(null), SaleReturn::CASH);

        $this->assertEquals(0, $this->salesHistory()['collected']['cash']);
        $this->assertEquals(0, $this->financeMethods()['byMethod']['cash']);
    }

    public function test_a_credit_refund_leaves_the_cash_line_alone(): void
    {
        // Nothing was handed over. The drawer still holds the ₦10,000.
        $this->refund($this->sale($this->customer()), SaleReturn::CREDIT);

        $this->assertEquals(10000, $this->salesHistory()['collected']['cash']);
        $this->assertEquals(10000, $this->financeMethods()['byMethod']['cash']);
    }

    public function test_a_cash_refund_comes_off_the_takings(): void
    {
        $this->refund($this->sale(null), SaleReturn::CASH);

        $this->assertEquals(0, $this->salesHistory()['cashCollected']);
    }

    public function test_the_refunded_amount_is_shown_so_the_figure_is_explained(): void
    {
        // A smaller number with nothing saying why is what sends people looking
        // for a discrepancy that is not there.
        $this->refund($this->sale(null), SaleReturn::CASH);

        $this->assertEquals(10000, $this->salesHistory()['cashRefunded']);
    }

    public function test_sales_history_and_financial_records_agree(): void
    {
        // The two pages compute cash separately, and staff read both. They must
        // not disagree about the same day.
        $this->refund($this->sale(null), SaleReturn::CASH);
        $this->refund($this->sale($this->customer()), SaleReturn::CREDIT);

        $this->assertEquals(
            $this->salesHistory()['collected']['cash'],
            $this->financeMethods()['byMethod']['cash'],
            'Sales History and Financial Records report different cash.'
        );
    }

    public function test_only_the_cash_half_is_taken_off(): void
    {
        $this->refund($this->sale(null), SaleReturn::CASH);            // 10,000 out
        $this->refund($this->sale($this->customer()), SaleReturn::CREDIT); // nothing out

        // Two ₦10,000 sales came in, one ₦10,000 refund went back out.
        $this->assertEquals(10000, $this->salesHistory()['collected']['cash']);
    }
}
