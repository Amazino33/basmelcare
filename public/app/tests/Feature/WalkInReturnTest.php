<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Giving money back.
 *
 * A return used to require a registered customer, and the reason was never
 * written down: the refund was always store credit, and store credit needs an
 * account to sit on. A walk-in has none, so they were refused rather than paid.
 *
 * The refund method is now explicit. Cash for a walk-in, either for a
 * registered customer - and the two must not be confused, because one empties
 * the drawer and the other does not.
 */
class WalkInReturnTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => ['branch_manager'], 'status' => 'active']);
    }

    private function customer(): Customer
    {
        return Customer::create([
            'name'  => 'ADAEZE OKON',
            'type'  => 'retail',
            'phone' => '080' . random_int(10000000, 99999999),
        ]);
    }

    /** A paid sale with one line, to a walk-in unless a customer is given. */
    private function sale(?Customer $customer = null, float $price = 4000, int $qty = 2): Sale
    {
        $product = Product::create([
            'name'          => 'PARACETAMOL ' . random_int(100, 999),
            'category_id'   => Category::firstOrCreate(['name' => 'MEDICINE'])->id,
            'selling_price' => $price,
            'reorder_level' => 1,
        ]);

        $batch = Batch::create([
            'product_id' => $product->id, 'batch_number' => 'B' . random_int(100, 999),
            'expiry_date' => now()->addYear(), 'cost_price' => 2500, 'quantity' => 50,
        ]);

        $sale = Sale::create([
            'invoice_number' => 'INV-' . now()->format('Ymd') . '-' . random_int(1000, 9999),
            'user_id'        => $this->staff()->id,
            'customer_id'    => $customer?->id,
            'total_amount'   => $price * $qty,
            'payment_method' => 'cash',
            'status'         => 'paid',
            'paid_at'        => now(),
        ]);

        SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $product->id, 'batch_id' => $batch->id,
            'quantity' => $qty, 'unit_price' => $price, 'cost_price' => 2500,
            'subtotal' => $price * $qty,
        ]);

        return $sale->fresh(['saleItems']);
    }

    private function page(?User $as = null)
    {
        return Livewire::actingAs($as ?? $this->staff())
            ->test(\App\Livewire\Sales\Index::class);
    }

    /** Open the return, put one unit in it, process it. */
    private function returnOne(Sale $sale, ?string $method = null)
    {
        $page = $this->page()->call('openReturn', $sale->id);

        if ($method) {
            $page->set('refundMethod', $method);
        }

        return $page
            ->set('returnQtys.' . $sale->saleItems->first()->id, 1)
            ->call('processReturn');
    }

    // ── the walk-in ─────────────────────────────────────────────────────

    public function test_a_walk_in_can_return_a_product(): void
    {
        $sale = $this->sale();

        $this->returnOne($sale);

        $this->assertSame(1, SaleReturn::count(), 'A walk-in was refused a return.');
    }

    public function test_the_return_form_opens_for_a_walk_in(): void
    {
        $this->page()
            ->call('openReturn', $this->sale()->id)
            ->assertSet('returnModal', true);
    }

    public function test_a_walk_in_is_refunded_in_cash(): void
    {
        // There is no account to credit. Anything else puts the goods back on
        // the shelf and gives the customer nothing.
        $this->returnOne($this->sale());

        $this->assertSame(SaleReturn::CASH, SaleReturn::sole()->refund_method);
    }

    public function test_a_walk_in_cannot_be_credited_even_if_the_form_says_so(): void
    {
        // The screen is not the authority: a stale or tampered value must not
        // book a credit that lands on nobody.
        $sale = $this->sale();

        $this->returnOne($sale, SaleReturn::CREDIT);

        $this->assertSame(SaleReturn::CASH, SaleReturn::sole()->refund_method);
    }

    public function test_the_stock_still_comes_back(): void
    {
        $sale  = $this->sale();
        $batch = $sale->saleItems->first()->batch;
        $before = $batch->quantity;

        $this->returnOne($sale);

        $this->assertSame($before + 1, $batch->fresh()->quantity);
    }

    public function test_the_pharmacy_can_still_insist_on_a_registered_customer(): void
    {
        // Some will want every return tied to a named person. The rule stays
        // available; it is simply no longer the only way the code works.
        AppSetting::set('return_require_customer', '1');

        $this->page()
            ->call('openReturn', $this->sale()->id)
            ->assertSet('returnModal', false);

        $this->assertSame(0, SaleReturn::count());
    }

    public function test_that_rule_does_not_block_a_registered_customer(): void
    {
        AppSetting::set('return_require_customer', '1');

        $this->returnOne($this->sale($this->customer()));

        $this->assertSame(1, SaleReturn::count());
    }

    // ── the registered customer keeps a choice ──────────────────────────

    public function test_a_registered_customer_is_credited_by_default(): void
    {
        $customer = $this->customer();

        $this->returnOne($this->sale($customer));

        $this->assertSame(SaleReturn::CREDIT, SaleReturn::sole()->refund_method);
        $this->assertEquals(4000, $customer->fresh()->credit_balance);
    }

    public function test_a_registered_customer_can_be_given_cash_instead(): void
    {
        $customer = $this->customer();

        $this->returnOne($this->sale($customer), SaleReturn::CASH);

        $this->assertSame(SaleReturn::CASH, SaleReturn::sole()->refund_method);
        $this->assertEquals(0, $customer->fresh()->credit_balance,
            'Cash was handed over and the account was credited as well.');
    }

    public function test_the_slip_says_which_one_happened(): void
    {
        // A slip reading "credit added" after a cash refund sends the customer
        // back to claim a balance that was never created.
        $this->returnOne($this->sale());

        $this->assertSame('Cash refunded', SaleReturn::sole()->refundLabel());

        $this->returnOne($this->sale($this->customer()));

        $this->assertSame(
            'Credit added to account',
            SaleReturn::orderByDesc('id')->first()->refundLabel()
        );
    }

    // ── the rules that already existed still hold ───────────────────────

    public function test_a_return_outside_the_window_is_still_refused(): void
    {
        AppSetting::set('return_window_hours', 48);

        $sale = $this->sale();
        $sale->forceFill(['created_at' => now()->subDays(5)])->save();

        $this->page()
            ->call('openReturn', $sale->id)
            ->assertSet('returnModal', false);
    }

    public function test_more_cannot_be_returned_than_was_bought(): void
    {
        $sale = $this->sale(qty: 2);

        $this->page()
            ->call('openReturn', $sale->id)
            ->set('returnQtys.' . $sale->saleItems->first()->id, 5)
            ->call('processReturn');

        $this->assertSame(0, SaleReturn::count());
    }

    public function test_a_cashier_cannot_process_a_return(): void
    {
        // Returns stay with elevated staff whoever the customer is.
        $sale = $this->sale();

        $this->page(User::factory()->create(['role' => ['cashier'], 'status' => 'active']))
            ->call('openReturn', $sale->id)
            ->assertSet('returnModal', false);

        $this->assertSame(0, SaleReturn::count());
    }
}
