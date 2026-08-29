<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InsuranceClaim;
use App\Models\InsurancePlan;
use App\Models\InsuranceSubscription;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cover at the counter.
 *
 * The service decides what is covered; this is about the till honouring that
 * answer once - not twice, not on a stale figure, and not while the scheme is
 * switched off.
 */
class InsuranceAtTheTillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AppSetting::set('insurance_enabled', '1');
    }

    private function cashier(): User
    {
        return User::factory()->create(['role' => ['cashier'], 'status' => 'active']);
    }

    private function customer(): Customer
    {
        return Customer::create([
            'name'  => 'ADAEZE OKON',
            'type'  => 'retail',
            'phone' => '080' . random_int(10000000, 99999999),
        ]);
    }

    private function covered(Customer $customer, array $planOverrides = []): InsuranceSubscription
    {
        $plan = InsurancePlan::create(array_merge([
            'name' => 'Bronze', 'code' => 'BRONZE-' . random_int(100, 999),
            'monthly_premium' => 5000, 'monthly_cover' => 10000,
            'copay_percent' => 0, 'waiting_days' => 30, 'grace_days' => 7,
            'is_active' => true,
        ], $planOverrides));

        $subscription = InsuranceSubscription::create([
            'customer_id'       => $customer->id,
            'insurance_plan_id' => $plan->id,
        ]);

        $subscription->recordPremium(5000, 'cash', at: now()->subDays((int) $plan->waiting_days + 1));

        return $subscription->fresh(['plan'])->refreshStatus();
    }

    /** A pending sale waiting at the cashier's screen. */
    private function pendingSale(?Customer $customer, float $price = 4000, float $cost = 2500): Sale
    {
        $product = Product::create([
            'name'          => 'PARACETAMOL ' . random_int(100, 999),
            'category_id'   => Category::firstOrCreate(['name' => 'MEDICINE'])->id,
            'selling_price' => $price,
            'reorder_level' => 1,
        ]);

        $batch = Batch::create([
            'product_id' => $product->id, 'batch_number' => 'B' . random_int(100, 999),
            'expiry_date' => now()->addYear(), 'cost_price' => $cost, 'quantity' => 50,
        ]);

        $sale = Sale::create([
            'invoice_number' => 'INV-' . now()->format('Ymd') . '-' . random_int(1000, 9999),
            'user_id'        => $this->cashier()->id,
            'customer_id'    => $customer?->id,
            'total_amount'   => $price,
            'status'         => 'pending',
        ]);

        SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $product->id, 'batch_id' => $batch->id,
            'quantity' => 1, 'unit_price' => $price, 'cost_price' => $cost, 'subtotal' => $price,
        ]);

        return $sale;
    }

    private function till(?User $as = null)
    {
        return Livewire::actingAs($as ?? $this->cashier())
            ->test(\App\Livewire\Cashier\Index::class);
    }

    // ── what the cashier is shown ───────────────────────────────────────

    public function test_a_customer_with_cover_is_flagged_when_the_sale_opens(): void
    {
        $customer = $this->customer();
        $this->covered($customer);
        $sale = $this->pendingSale($customer);

        $this->till()
            ->call('openPayment', $sale->id)
            ->assertSet('insuranceQuote.covered', 4000.0);
    }

    public function test_a_customer_with_no_cover_is_not_mentioned_at_all(): void
    {
        // A till that never sells insurance must look exactly as it did.
        $sale = $this->pendingSale($this->customer());

        $this->till()
            ->call('openPayment', $sale->id)
            ->assertSet('insuranceQuote', null);
    }

    public function test_a_walk_in_sale_has_no_cover_to_show(): void
    {
        $sale = $this->pendingSale(null);

        $this->till()
            ->call('openPayment', $sale->id)
            ->assertSet('insuranceQuote', null);
    }

    public function test_attaching_a_covered_customer_brings_their_cover_with_them(): void
    {
        // Cover belongs to the customer, so attaching one to a walk-in sale is
        // exactly when it starts applying.
        $customer = $this->customer();
        $this->covered($customer);
        $sale = $this->pendingSale(null);

        $this->till()
            ->call('openPayment', $sale->id)
            ->assertSet('insuranceQuote', null)
            ->call('attachCustomer', $customer->id)
            ->assertSet('insuranceQuote.covered', 4000.0);
    }

    public function test_removing_the_customer_takes_the_cover_away_too(): void
    {
        $customer = $this->customer();
        $this->covered($customer);
        $sale = $this->pendingSale($customer);

        $this->till()
            ->call('openPayment', $sale->id)
            ->call('detachCustomer')
            ->assertSet('insuranceQuote', null);
    }

    public function test_a_lapsed_customer_is_told_why_rather_than_ignored(): void
    {
        // The cashier has to be able to say "your premium is overdue" instead
        // of guessing on the customer's behalf.
        $customer = $this->customer();
        $this->covered($customer, ['waiting_days' => 0, 'grace_days' => 0]);
        $this->travel(45)->days();

        $sale = $this->pendingSale($customer);

        $component = $this->till()->call('openPayment', $sale->id);

        $this->assertSame(0.0, $component->get('insuranceQuote.covered'));
        $this->assertStringContainsString('overdue', $component->get('insuranceQuote.reason'));
    }

    // ── taking the money ────────────────────────────────────────────────

    public function test_the_customer_only_pays_what_cover_did_not(): void
    {
        $customer = $this->customer();
        $this->covered($customer, ['monthly_cover' => 3000]);
        $sale = $this->pendingSale($customer, price: 4000);

        $this->till()
            ->call('openPayment', $sale->id)
            ->set('cash_tendered', 1000)
            ->call('processPayment');

        $sale->refresh();

        $this->assertSame('paid', $sale->status);
        $this->assertArrayNotHasKey('shortfall', $sale->payment_details,
            'The customer was billed for medicine their cover paid for.');
    }

    public function test_the_receipt_records_what_cover_paid(): void
    {
        $customer = $this->customer();
        $this->covered($customer);
        $sale = $this->pendingSale($customer, price: 4000);

        $this->till()
            ->call('openPayment', $sale->id)
            ->set('cash_tendered', 0.01)
            ->call('processPayment');

        $details = $sale->fresh()->payment_details;

        $this->assertSame(4000.0, (float) $details['insurance']['amount']);
        $this->assertSame('Bronze', $details['insurance']['plan']);
    }

    public function test_paying_writes_one_claim_against_the_subscription(): void
    {
        $customer     = $this->customer();
        $subscription = $this->covered($customer);
        $sale         = $this->pendingSale($customer, price: 4000, cost: 2500);

        $this->till()
            ->call('openPayment', $sale->id)
            ->set('cash_tendered', 0.01)
            ->call('processPayment');

        $claim = InsuranceClaim::where('insurance_subscription_id', $subscription->id)->sole();

        $this->assertEquals(4000, $claim->amount);
        $this->assertEquals(2500, $claim->cost_amount, 'The claim did not book what the medicine cost.');
        $this->assertSame($sale->id, $claim->sale_id);
    }

    public function test_the_cover_is_spent_once_the_sale_is_paid(): void
    {
        $customer     = $this->customer();
        $subscription = $this->covered($customer);
        $sale         = $this->pendingSale($customer, price: 4000);

        $this->till()
            ->call('openPayment', $sale->id)
            ->set('cash_tendered', 0.01)
            ->call('processPayment');

        $this->assertSame(6000.0, $subscription->fresh(['plan'])->coverRemaining());
    }

    public function test_a_second_sale_cannot_spend_the_same_cover_again(): void
    {
        $customer     = $this->customer();
        $subscription = $this->covered($customer, ['monthly_cover' => 4000]);

        foreach ([1, 2] as $_) {
            $sale = $this->pendingSale($customer, price: 4000);
            $this->till()
                ->call('openPayment', $sale->id)
                ->set('cash_tendered', 4000)
                ->call('processPayment');
        }

        $this->assertEquals(4000, $subscription->fresh()->cover_used);
        $this->assertSame(1, InsuranceClaim::count(), 'The second sale claimed against cover that was gone.');
    }

    public function test_cover_is_taken_at_payment_not_when_the_screen_opened(): void
    {
        // The quote on screen can be stale - the same customer's cover may have
        // been spent on an online order since the modal opened. Paying out on
        // the stale figure is how a plan pays twice.
        $customer     = $this->customer();
        $subscription = $this->covered($customer, ['monthly_cover' => 4000]);
        $sale         = $this->pendingSale($customer, price: 4000);

        $component = $this->till()->call('openPayment', $sale->id);
        $this->assertSame(4000.0, $component->get('insuranceQuote.covered'));

        // Meanwhile, elsewhere, the whole cover is used.
        $subscription->fresh(['plan'])->drawDown(4000);

        $component->set('cash_tendered', 4000)->call('processPayment');

        $this->assertSame(0, InsuranceClaim::count());
        $this->assertEquals(4000, $subscription->fresh()->cover_used, 'Cover was overdrawn.');

        // The telling assertion. Honouring the stale figure would have knocked
        // ₦4,000 off a ₦4,000 sale and handed the customer their money back
        // as change for medicine nothing paid for.
        $details = $sale->fresh()->payment_details;
        $this->assertArrayNotHasKey('change_given', $details,
            'The customer was given change against cover that was already spent.');
        $this->assertArrayNotHasKey('insurance', $details);
    }

    // ── the switch ──────────────────────────────────────────────────────

    public function test_nothing_happens_at_the_till_while_the_scheme_is_off(): void
    {
        $customer = $this->customer();
        $this->covered($customer);
        AppSetting::set('insurance_enabled', '0');

        $sale = $this->pendingSale($customer, price: 4000);

        $this->till()
            ->call('openPayment', $sale->id)
            ->assertSet('insuranceQuote', null)
            ->set('cash_tendered', 4000)
            ->call('processPayment');

        $this->assertSame(0, InsuranceClaim::count());
        $this->assertSame('paid', $sale->fresh()->status);
    }
}
