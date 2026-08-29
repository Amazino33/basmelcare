<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InsuranceClaim;
use App\Models\InsurancePlan;
use App\Models\InsuranceSubscription;
use App\Models\Product;
use App\Models\User;
use App\Services\InsuranceCover;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Monthly cover: the premium comes in, the medicine goes out.
 *
 * The pharmacy carries the risk here and has already paid for the stock, so
 * every limit is tested from the direction of somebody trying to get more out
 * than they put in - subscribing the morning they fall ill, claiming past the
 * ceiling, claiming after they stopped paying, or spending the same cover
 * twice at the counter and online.
 */
class InsuranceCoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AppSetting::set('insurance_enabled', '1');
    }

    private function plan(array $overrides = []): InsurancePlan
    {
        return InsurancePlan::create(array_merge([
            'name'            => 'Bronze',
            'code'            => 'BRONZE',
            'monthly_premium' => 5000,
            'monthly_cover'   => 10000,
            'copay_percent'   => 0,
            'waiting_days'    => 30,
            'grace_days'      => 7,
            'is_active'       => true,
        ], $overrides));
    }

    private function customer(): Customer
    {
        return Customer::create([
            'name'  => 'ADAEZE OKON',
            'type'  => 'retail',
            'phone' => '080' . random_int(10000000, 99999999),
        ]);
    }

    private function product(float $price = 2400, ?int $categoryId = null): Product
    {
        return Product::create([
            'name'          => 'PRODUCT ' . random_int(1000, 9999),
            'category_id'   => $categoryId ?? Category::firstOrCreate(['name' => 'MEDICINE'])->id,
            'selling_price' => $price,
            'reorder_level' => 1,
        ]);
    }

    /** A subscription that has paid and is past its waiting period. */
    private function covered(array $planOverrides = []): InsuranceSubscription
    {
        $plan = $this->plan($planOverrides);

        $subscription = InsuranceSubscription::create([
            'customer_id'       => $this->customer()->id,
            'insurance_plan_id' => $plan->id,
        ]);

        $subscription->recordPremium(
            (float) $plan->monthly_premium,
            'cash',
            recordedBy: User::factory()->create()->id,
            at: now()->subDays((int) $plan->waiting_days + 1),
        );

        return $subscription->fresh(['plan'])->refreshStatus();
    }

    private function lines(array $pairs): array
    {
        return array_map(fn ($p) => [
            'product'  => $p[0],
            'subtotal' => $p[1],
            'cost'     => $p[2] ?? 0,
        ], $pairs);
    }

    private function cover(): InsuranceCover
    {
        return app(InsuranceCover::class);
    }

    // ── the switch ──────────────────────────────────────────────────────

    public function test_nothing_is_covered_while_the_feature_is_off(): void
    {
        // Built now, sold later. Until the pharmacy switches it on, a
        // subscription must not quietly start paying for medicine.
        $subscription = $this->covered();
        AppSetting::set('insurance_enabled', '0');

        $quote = $this->cover()->quote($subscription, $this->lines([[$this->product(), 2400]]));

        $this->assertSame(0.0, $quote['covered']);
        $this->assertSame(2400.0, $quote['payable']);
    }

    public function test_a_customer_with_no_cover_simply_pays(): void
    {
        $quote = $this->cover()->quote(null, $this->lines([[$this->product(), 2400]]));

        $this->assertSame(0.0, $quote['covered']);
        $this->assertSame(2400.0, $quote['payable']);
    }

    // ── ordinary use ────────────────────────────────────────────────────

    public function test_cover_pays_for_the_medicine(): void
    {
        $subscription = $this->covered();

        $quote = $this->cover()->quote($subscription, $this->lines([[$this->product(), 2400]]));

        $this->assertSame(2400.0, $quote['covered']);
        $this->assertSame(0.0, $quote['payable']);
    }

    public function test_what_is_spent_comes_off_the_month_s_cover(): void
    {
        $subscription = $this->covered();

        $this->cover()->apply($subscription, $this->lines([[$this->product(), 2400]]));

        $this->assertSame(7600.0, $subscription->fresh(['plan'])->coverRemaining());
    }

    public function test_a_claim_records_what_the_medicine_cost_the_pharmacy(): void
    {
        // Premiums minus cost is the only honest answer to whether the scheme
        // is losing money, and batch costs cannot be recovered later.
        $subscription = $this->covered();

        $this->cover()->apply($subscription, $this->lines([[$this->product(), 2400, 1500]]));

        $this->assertEquals(1500, InsuranceClaim::first()->cost_amount);
    }

    public function test_the_claim_is_tied_to_the_receipt_it_came_from(): void
    {
        $subscription = $this->covered();

        $this->cover()->apply($subscription, $this->lines([[$this->product(), 2400]]), saleId: null, orderId: 0 ?: null);

        $this->assertSame(1, InsuranceClaim::where('insurance_subscription_id', $subscription->id)->count());
    }

    // ── the ceiling ─────────────────────────────────────────────────────

    public function test_cover_stops_at_the_month_s_ceiling(): void
    {
        // Without this one subscriber on an expensive course takes a year of
        // everybody else's premiums.
        $subscription = $this->covered();

        $quote = $this->cover()->quote($subscription, $this->lines([[$this->product(), 25000]]));

        $this->assertSame(10000.0, $quote['covered']);
        $this->assertSame(15000.0, $quote['payable'], 'The customer was not asked for the excess.');
    }

    public function test_the_ceiling_holds_across_several_visits(): void
    {
        $subscription = $this->covered();

        $this->cover()->apply($subscription, $this->lines([[$this->product(), 6000]]));
        $second = $this->cover()->apply($subscription->fresh(['plan']), $this->lines([[$this->product(), 6000]]));

        $this->assertSame(4000.0, $second['covered']);
        $this->assertSame(0.0, $subscription->fresh(['plan'])->coverRemaining());
    }

    public function test_once_the_cover_is_gone_the_customer_pays_in_full(): void
    {
        $subscription = $this->covered();
        $this->cover()->apply($subscription, $this->lines([[$this->product(), 10000]]));

        $quote = $this->cover()->quote($subscription->fresh(['plan']), $this->lines([[$this->product(), 2400]]));

        $this->assertSame(0.0, $quote['covered']);
        $this->assertSame(2400.0, $quote['payable']);
        $this->assertStringContainsString('used up', $quote['reason']);
    }

    public function test_cover_cannot_be_overdrawn_by_two_tills_at_once(): void
    {
        // The counter and the shop can be spending the same cover. Two reads
        // of "₦10,000 left" must not each be allowed ₦10,000.
        $subscription = $this->covered();

        $a = $this->cover()->apply($subscription, $this->lines([[$this->product(), 8000]]));
        $b = $this->cover()->apply($subscription, $this->lines([[$this->product(), 8000]]));

        $this->assertSame(10000.0, round($a['covered'] + $b['covered'], 2));
        $this->assertEquals(10000, $subscription->fresh()->cover_used);
    }

    // ── waiting, lapsing, cancelling ────────────────────────────────────

    public function test_nobody_can_subscribe_in_the_morning_and_claim_that_afternoon(): void
    {
        // The single biggest way to lose money on this: join the day you fall
        // ill, take the full cover, never pay again.
        $plan = $this->plan(['waiting_days' => 30]);

        $subscription = InsuranceSubscription::create([
            'customer_id'       => $this->customer()->id,
            'insurance_plan_id' => $plan->id,
        ]);
        $subscription->recordPremium(5000, 'cash');

        $quote = $this->cover()->quote($subscription->fresh(['plan']), $this->lines([[$this->product(), 2400]]));

        $this->assertSame(0.0, $quote['covered']);
        $this->assertStringContainsString('Cover starts on', $quote['reason']);
    }

    public function test_cover_begins_when_the_waiting_period_ends(): void
    {
        $subscription = $this->covered(['waiting_days' => 30]);

        $this->assertTrue($subscription->isClaimable());
        $this->assertSame(InsuranceSubscription::ACTIVE, $subscription->status);
    }

    public function test_signing_up_is_not_the_same_as_paying(): void
    {
        $subscription = InsuranceSubscription::create([
            'customer_id'       => $this->customer()->id,
            'insurance_plan_id' => $this->plan()->id,
        ]);

        $quote = $this->cover()->quote($subscription->fresh(['plan']), $this->lines([[$this->product(), 2400]]));

        $this->assertSame(0.0, $quote['covered']);
        $this->assertStringContainsString('not been paid', $quote['reason']);
    }

    public function test_cover_ends_when_the_premium_stops(): void
    {
        $subscription = $this->covered(['waiting_days' => 0, 'grace_days' => 7]);

        // Two months on, with only the one month ever paid for.
        $this->travel(70)->days();

        $quote = $this->cover()->quote($subscription->fresh(['plan']), $this->lines([[$this->product(), 2400]]));

        $this->assertSame(0.0, $quote['covered']);
        $this->assertStringContainsString('overdue', $quote['reason']);
    }

    public function test_a_few_days_late_is_still_covered(): void
    {
        // People pay late. Cutting cover off at midnight on the last day turns
        // a busy week into an uncovered emergency.
        $subscription = $this->covered(['waiting_days' => 0, 'grace_days' => 7]);

        $this->travel(33)->days();

        $this->assertTrue($subscription->fresh(['plan'])->isClaimable());
    }

    public function test_past_the_grace_period_it_lapses(): void
    {
        $subscription = $this->covered(['waiting_days' => 0, 'grace_days' => 7]);

        $this->travel(40)->days();

        $fresh = $subscription->fresh(['plan'])->refreshStatus();

        $this->assertFalse($fresh->isClaimable());
        $this->assertSame(InsuranceSubscription::LAPSED, $fresh->status);
    }

    public function test_cancelled_cover_pays_for_nothing(): void
    {
        $subscription = $this->covered();
        $subscription->cancel('Customer asked to stop.');

        $quote = $this->cover()->quote($subscription->fresh(['plan']), $this->lines([[$this->product(), 2400]]));

        $this->assertSame(0.0, $quote['covered']);
        $this->assertStringContainsString('cancelled', $quote['reason']);
    }

    // ── the months themselves ───────────────────────────────────────────

    public function test_a_month_starts_the_day_it_is_paid_for(): void
    {
        // Joining on the 20th buys a month, not the ten days left of one.
        $plan = $this->plan();
        $subscription = InsuranceSubscription::create([
            'customer_id'       => $this->customer()->id,
            'insurance_plan_id' => $plan->id,
        ]);

        $premium = $subscription->recordPremium(5000, 'cash', at: now()->setDay(20));

        $this->assertSame(
            now()->setDay(20)->startOfDay()->addMonth()->subDay()->toDateString(),
            $premium->period_end->toDateString()
        );
    }

    public function test_unused_cover_does_not_carry_into_next_month(): void
    {
        // What makes the pool work: this month's premiums pay this month's
        // claims. Rolling over would let a year of unused cover be spent at
        // once on a single visit.
        $subscription = $this->covered(['waiting_days' => 0]);
        $this->cover()->apply($subscription, $this->lines([[$this->product(), 2000]]));

        $subscription->fresh(['plan'])->recordPremium(5000, 'cash', at: now()->addMonth());

        $this->assertSame(10000.0, $subscription->fresh(['plan'])->coverRemaining());
    }

    public function test_paying_early_adds_a_month_rather_than_losing_the_days(): void
    {
        $subscription = $this->covered(['waiting_days' => 0]);
        $firstEnd = $subscription->period_end->copy();

        $subscription->fresh(['plan'])->recordPremium(5000, 'cash');

        $this->assertSame(
            $firstEnd->copy()->addMonth()->toDateString(),
            $subscription->fresh()->period_end->toDateString()
        );
    }

    public function test_the_same_month_cannot_be_paid_for_twice(): void
    {
        $subscription = $this->covered(['waiting_days' => 0]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        // Same period start as the premium already recorded.
        $subscription->premiums()->create([
            'amount'       => 5000,
            'period_start' => $subscription->period_start,
            'period_end'   => $subscription->period_end,
            'method'       => 'cash',
            'paid_at'      => now(),
        ]);
    }

    // ── what cover is for ───────────────────────────────────────────────

    public function test_excluded_categories_are_not_covered(): void
    {
        // The premium was collected for medicine, not for cosmetics.
        $cosmetics = Category::create(['name' => 'COSMETICS']);
        $subscription = $this->covered(['excluded_categories' => [$cosmetics->id]]);

        $quote = $this->cover()->quote($subscription, $this->lines([
            [$this->product(2400), 2400],
            [$this->product(3000, $cosmetics->id), 3000],
        ]));

        $this->assertSame(2400.0, $quote['covered']);
        $this->assertSame(3000.0, $quote['payable']);
    }

    public function test_a_co_pay_leaves_the_subscriber_a_share_to_pay(): void
    {
        $subscription = $this->covered(['copay_percent' => 20]);

        $quote = $this->cover()->quote($subscription, $this->lines([[$this->product(), 2000]]));

        $this->assertSame(400.0, $quote['copay']);
        $this->assertSame(1600.0, $quote['covered']);
        $this->assertSame(400.0, $quote['payable']);
    }

    public function test_a_co_pay_stretches_the_cover_rather_than_draining_it(): void
    {
        // Taken off the top, so a 20% co-pay makes ₦10,000 of cover reach
        // ₦12,500 of medicine.
        $subscription = $this->covered(['copay_percent' => 20]);

        $quote = $this->cover()->quote($subscription, $this->lines([[$this->product(), 12500]]));

        $this->assertSame(10000.0, $quote['covered']);
        $this->assertSame(2500.0, $quote['payable']);
    }

    // ── giving it back ──────────────────────────────────────────────────

    public function test_a_returned_item_gives_the_cover_back(): void
    {
        $subscription = $this->covered();
        $this->cover()->apply($subscription, $this->lines([[$this->product(), 4000]]));

        $subscription->fresh(['plan'])->refund(4000);

        $this->assertSame(10000.0, $subscription->fresh(['plan'])->coverRemaining());
    }

    public function test_a_refund_cannot_push_the_cover_past_full(): void
    {
        $subscription = $this->covered();
        $this->cover()->apply($subscription, $this->lines([[$this->product(), 1000]]));

        $subscription->fresh(['plan'])->refund(5000);

        $this->assertEquals(0, $subscription->fresh()->cover_used);
        $this->assertSame(10000.0, $subscription->fresh(['plan'])->coverRemaining());
    }

    // ── finding a customer's cover ──────────────────────────────────────

    public function test_a_lapsed_subscription_is_still_found_so_it_can_be_explained(): void
    {
        // Returning nothing would leave the cashier telling the customer they
        // never had cover, when the truth is they owe a premium.
        $subscription = $this->covered(['waiting_days' => 0, 'grace_days' => 0]);
        $this->travel(40)->days();

        $found = InsuranceSubscription::forCustomer($subscription->customer_id);

        $this->assertNotNull($found);
        $this->assertSame(InsuranceSubscription::LAPSED, $found->status);
    }

    public function test_a_customer_with_no_subscription_has_none(): void
    {
        $this->assertNull(InsuranceSubscription::forCustomer($this->customer()->id));
    }
}
