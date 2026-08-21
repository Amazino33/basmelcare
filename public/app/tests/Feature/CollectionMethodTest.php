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
 * How the money was taken: cash, card or transfer.
 *
 * payment_details is JSON whose shape has changed over time. Anything that
 * cannot be attributed to a method is reported as "not recorded" rather than
 * dropped - on this system only 15 of 26 settled sales carry a method, so a
 * breakdown that silently omitted the rest would show a third of the money and
 * look complete.
 */
class CollectionMethodTest extends TestCase
{
    use RefreshDatabase;

    private function sell(float $amount, ?array $paymentDetails, string $status = 'completed'): Sale
    {
        $product = Product::create([
            'name' => 'Item ' . random_int(1000, 9999),
            'category_id' => Category::firstOrCreate(['name' => 'General'])->id,
            'selling_price' => $amount, 'reorder_level' => 1,
        ]);

        $batch = Batch::create([
            'product_id' => $product->id, 'batch_number' => 'B-' . random_int(1000, 9999),
            'expiry_date' => now()->addYear(), 'cost_price' => $amount / 2, 'quantity' => 10,
        ]);

        $sale = Sale::create([
            'invoice_number'  => 'INV-' . random_int(10000, 99999),
            'user_id'         => User::factory()->create(['role' => ['cashier']])->id,
            'total_amount'    => $amount,
            'coupon_discount' => 0,
            'status'          => $status,
            'payment_details' => $paymentDetails,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $product->id, 'batch_id' => $batch->id,
            'quantity' => 1, 'unit_price' => $amount, 'cost_price' => $amount / 2, 'subtotal' => $amount,
        ]);

        return $sale;
    }

    private function methods(): array
    {
        $auditor = User::factory()->create(['role' => ['auditor'], 'status' => 'active']);

        return Livewire::actingAs($auditor)
            ->test(\App\Livewire\Finance\Index::class)
            ->viewData('f')['methods'];
    }

    public function test_a_single_method_sale_is_attributed(): void
    {
        $this->sell(1500, ['cash' => 1500]);

        $m = $this->methods();

        $this->assertEquals(1500, $m['byMethod']['cash']);
        $this->assertEquals(0, $m['byMethod']['card']);
        $this->assertEquals(0, $m['unrecorded']);
    }

    public function test_a_split_sale_is_shared_across_methods(): void
    {
        $this->sell(45000, ['cash' => 22500, 'transfer' => 22500]);

        $m = $this->methods();

        $this->assertEquals(22500, $m['byMethod']['cash']);
        $this->assertEquals(22500, $m['byMethod']['transfer']);
        $this->assertEquals(45000, $m['methodTotal']);
        $this->assertEquals(0, $m['unrecorded']);
    }

    public function test_store_credit_is_not_counted_as_money_taken(): void
    {
        // Credit was collected on an earlier day; counting it again inflates today.
        $this->sell(1000, ['cash' => 800, 'credit' => 200]);

        $m = $this->methods();

        $this->assertEquals(800, $m['byMethod']['cash']);
        $this->assertEquals(200, $m['storeCredit']);
        $this->assertEquals(800, $m['methodTotal'], 'Store credit leaked into the method total.');
    }

    public function test_change_handed_back_is_reported(): void
    {
        $this->sell(1000, ['cash' => 1500, 'change_given' => 500]);

        $m = $this->methods();

        $this->assertEquals(1500, $m['byMethod']['cash']);
        $this->assertEquals(500, $m['changeGiven']);
    }

    public function test_sales_with_no_payment_details_show_as_not_recorded(): void
    {
        $this->sell(5000, null);

        $m = $this->methods();

        $this->assertEquals(0, $m['methodTotal']);
        $this->assertEquals(5000, $m['unrecorded'], 'Money with no method must still be reported.');
        $this->assertSame(0, $m['salesWithMethod']);
        $this->assertSame(1, $m['salesTotal']);
    }

    public function test_a_legacy_shape_is_treated_as_not_recorded(): void
    {
        // Written by an older version of the till; no cash/card/transfer keys.
        $this->sell(1500, ['method' => 'cash', 'balance' => 500, 'paid_now' => 1000]);

        $m = $this->methods();

        $this->assertEquals(0, $m['methodTotal'], 'A shape we cannot read must not be guessed at.');
        $this->assertEquals(1500, $m['unrecorded']);
    }

    public function test_the_breakdown_reconciles_to_the_settled_total(): void
    {
        $this->sell(1000, ['cash' => 1000]);
        $this->sell(2000, ['transfer' => 2000]);
        $this->sell(5000, null);            // no method recorded

        $m = $this->methods();

        $this->assertEquals(3000, $m['methodTotal']);
        $this->assertEquals(5000, $m['unrecorded']);
        // Recorded + unrecorded must account for every settled naira.
        $this->assertEquals(
            $m['settledTotal'],
            $m['methodTotal'] + $m['unrecorded'],
            'The breakdown does not add up to the settled total.'
        );
    }

    public function test_unsettled_sales_are_excluded(): void
    {
        $this->sell(9000, ['cash' => 9000], status: 'pending');

        $m = $this->methods();

        $this->assertEquals(0, $m['methodTotal']);
        $this->assertEquals(0, $m['settledTotal']);
    }

    public function test_debt_repayments_are_included_and_shown_separately(): void
    {
        $sale = $this->sell(1000, ['cash' => 1000]);

        $customer = Customer::create([
            'name' => 'Owes Money', 'type' => 'retail', 'phone' => '08031112233',
        ]);
        $debt = Debt::create([
            'sale_id' => $sale->id, 'customer_id' => $customer->id,
            'amount_owed' => 5000, 'amount_paid' => 0, 'status' => 'partial',
        ]);
        DebtPayment::create([
            'debt_id' => $debt->id, 'amount' => 2000,
            'payment_method' => 'transfer',
            'received_by' => User::factory()->create()->id,
        ]);

        $m = $this->methods();

        $this->assertEquals(1000, $m['byMethod']['cash']);
        $this->assertEquals(2000, $m['byMethod']['transfer'], 'Debt repayment was not counted.');
        $this->assertEquals(2000, $m['debtByMethod']['transfer'], 'Debt repayment not shown separately.');
        $this->assertEquals(0, $m['debtByMethod']['cash']);
    }

    public function test_the_page_warns_when_money_has_no_method(): void
    {
        $this->sell(5000, null);

        $auditor = User::factory()->create(['role' => ['auditor'], 'status' => 'active']);
        $html = Livewire::actingAs($auditor)->test(\App\Livewire\Finance\Index::class)->html();

        $this->assertStringContainsString('Method not recorded', $html);
        $this->assertStringContainsString('Collected by method', $html);
    }
}
