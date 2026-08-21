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
 * A part-payment records the same money twice by design: once in the sale's
 * payment_details (what the till took) and once as a DebtPayment against the
 * debt it created. Both rows are correct, but anything summing sale payments
 * AND debt payments counts that money twice - which made reported cash exceed
 * the cash actually in the drawer.
 *
 * The opening payment is flagged so cash reporting skips it. Later repayments
 * are separate money and must still count.
 */
class PartPaymentCashTest extends TestCase
{
    use RefreshDatabase;

    private function payPartially(float $total, float $paid, float $discount = 0): Sale
    {
        $product = Product::create([
            'name' => 'PARACETAMOL 500MG',
            'category_id' => Category::firstOrCreate(['name' => 'General'])->id,
            'selling_price' => $total, 'reorder_level' => 1,
        ]);

        $batch = Batch::create([
            'product_id' => $product->id, 'batch_number' => 'B1',
            'expiry_date' => now()->addYear(), 'cost_price' => $total / 2, 'quantity' => 50,
        ]);

        $customer = Customer::create([
            'name' => 'Owes Money', 'type' => 'retail', 'phone' => '08031112233',
        ]);

        $sale = Sale::create([
            'invoice_number' => 'INV-' . random_int(10000, 99999),
            'user_id' => User::factory()->create(['role' => ['cashier']])->id,
            'customer_id' => $customer->id,
            'total_amount' => $total,
            'coupon_discount' => $discount,
            'status' => 'paid',
            'paid_at' => now(),
            // What the till actually took.
            'payment_details' => ['cash' => $paid, 'shortfall' => ($total - $discount) - $paid],
        ]);

        SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $product->id, 'batch_id' => $batch->id,
            'quantity' => 1, 'unit_price' => $total, 'cost_price' => $total / 2, 'subtotal' => $total,
        ]);

        $debt = Debt::create([
            'sale_id' => $sale->id, 'customer_id' => $customer->id,
            'amount_owed' => $total - $discount,
            'amount_paid' => $paid,
            'status' => 'partial',
        ]);

        // Mirrors what the cashier writes at the till.
        DebtPayment::create([
            'debt_id' => $debt->id, 'amount' => $paid,
            'payment_method' => 'cash', 'at_point_of_sale' => true,
            'received_by' => User::factory()->create()->id,
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

    public function test_the_opening_payment_is_counted_once_not_twice(): void
    {
        // ₦2,000 sale, ₦700 discount, customer pays ₦800 and owes ₦500.
        $this->payPartially(total: 2000, paid: 800, discount: 700);

        $m = $this->methods();

        $this->assertEquals(800, $m['byMethod']['cash'],
            'Reported cash does not match what the till took.');
    }

    public function test_a_later_repayment_still_counts(): void
    {
        $sale = $this->payPartially(total: 2000, paid: 800, discount: 700);
        $debt = Debt::where('sale_id', $sale->id)->firstOrFail();

        // Customer comes back and settles the ₦500.
        DebtPayment::create([
            'debt_id' => $debt->id, 'amount' => 500,
            'payment_method' => 'cash', 'at_point_of_sale' => false,
            'received_by' => User::factory()->create()->id,
        ]);

        $m = $this->methods();

        $this->assertEquals(1300, $m['byMethod']['cash'], '800 at the till plus 500 repaid.');
        $this->assertEquals(500, $m['debtByMethod']['cash'],
            'Only the genuine repayment should show as debt recovery.');
    }

    public function test_a_repayment_by_transfer_is_attributed_correctly(): void
    {
        $sale = $this->payPartially(total: 2000, paid: 800, discount: 700);
        $debt = Debt::where('sale_id', $sale->id)->firstOrFail();

        DebtPayment::create([
            'debt_id' => $debt->id, 'amount' => 500,
            'payment_method' => 'transfer', 'at_point_of_sale' => false,
            'received_by' => User::factory()->create()->id,
        ]);

        $m = $this->methods();

        $this->assertEquals(800, $m['byMethod']['cash']);
        $this->assertEquals(500, $m['byMethod']['transfer']);
    }

    public function test_the_discount_is_deducted_from_revenue(): void
    {
        $this->payPartially(total: 2000, paid: 800, discount: 700);

        $auditor = User::factory()->create(['role' => ['auditor'], 'status' => 'active']);
        $f = Livewire::actingAs($auditor)
            ->test(\App\Livewire\Finance\Index::class)->viewData('f');

        // The customer was billed 1,300 after the discount, not 2,000.
        $this->assertEquals(1300, $f['revenue']);
    }

    public function test_money_in_matches_what_was_actually_taken(): void
    {
        $this->payPartially(total: 2000, paid: 800, discount: 700);

        $auditor = User::factory()->create(['role' => ['auditor'], 'status' => 'active']);
        $f = Livewire::actingAs($auditor)
            ->test(\App\Livewire\Finance\Index::class)->viewData('f');

        // Billed 1,300, of which 500 went on credit, so 800 came in.
        $this->assertEquals(800, $f['collected'],
            'Money in should equal the cash taken, not the amount billed.');
    }

    public function test_the_two_figures_agree_with_each_other(): void
    {
        $this->payPartially(total: 2000, paid: 800, discount: 700);

        $auditor = User::factory()->create(['role' => ['auditor'], 'status' => 'active']);
        $f = Livewire::actingAs($auditor)
            ->test(\App\Livewire\Finance\Index::class)->viewData('f');

        // "Money in" and the method breakdown describe the same money, so a
        // discrepancy between them is what the cashier would notice first.
        $this->assertEquals(
            $f['collected'],
            $f['methods']['byMethod']['cash'],
            'Money in disagrees with the cash breakdown.'
        );
    }
}
