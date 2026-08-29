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
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The figures that decide whether the scheme survives.
 *
 * Two results are reported and they must not be conflated: cash (premiums less
 * what the medicine cost) says whether the scheme is solvent; trading
 * (premiums less what the medicine would have sold for) says what the shop
 * gave up. Reporting only the kinder one would be flattering nonsense.
 */
class InsuranceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AppSetting::set('insurance_enabled', '1');
    }

    private function user(array $roles): User
    {
        return User::factory()->create(['role' => $roles, 'status' => 'active']);
    }

    private function covered(array $planOverrides = []): InsuranceSubscription
    {
        $plan = InsurancePlan::create(array_merge([
            'name' => 'Bronze', 'code' => 'BRONZE-' . random_int(100, 999),
            'monthly_premium' => 5000, 'monthly_cover' => 10000,
            'copay_percent' => 0, 'waiting_days' => 0, 'grace_days' => 7,
            'is_active' => true,
        ], $planOverrides));

        $customer = Customer::create([
            'name'  => 'CUSTOMER ' . random_int(1000, 9999),
            'type'  => 'retail',
            'phone' => '080' . random_int(10000000, 99999999),
        ]);

        $subscription = InsuranceSubscription::create([
            'customer_id'       => $customer->id,
            'insurance_plan_id' => $plan->id,
        ]);

        $subscription->recordPremium((float) $plan->monthly_premium, 'cash');

        return $subscription->fresh(['plan']);
    }

    private function claim(InsuranceSubscription $subscription, float $value, float $cost): void
    {
        $product = Product::create([
            'name'          => 'PRODUCT ' . random_int(1000, 9999),
            'category_id'   => Category::firstOrCreate(['name' => 'MEDICINE'])->id,
            'selling_price' => $value,
            'reorder_level' => 1,
        ]);

        app(InsuranceCover::class)->apply($subscription, [
            ['product' => $product, 'subtotal' => $value, 'cost' => $cost],
        ]);
    }

    private function report(?User $as = null)
    {
        return Livewire::actingAs($as ?? $this->user(['auditor']))
            ->test(\App\Livewire\Insurance\Report::class);
    }

    /** The figures themselves, not the HTML they end up in. */
    private function figures(array $range = []): array
    {
        $component = $this->report();

        foreach ($range as $key => $value) {
            $component->set($key, $value);
        }

        return $component->instance()->figures();
    }

    // ── the two results ─────────────────────────────────────────────────

    public function test_the_cash_result_is_premiums_less_what_the_medicine_cost(): void
    {
        $subscription = $this->covered();       // ₦5,000 in
        $this->claim($subscription, 4000, 2500); // ₦2,500 of stock consumed

        $this->assertSame(2500.0, $this->figures()['cashResult']);
    }

    public function test_the_trading_result_is_measured_against_the_shelf_price(): void
    {
        // What the shop would have taken had they simply walked in and bought.
        $subscription = $this->covered();
        $this->claim($subscription, 4000, 2500);

        $this->assertSame(1000.0, $this->figures()['tradingResult']);
    }

    public function test_a_scheme_paying_out_more_than_it_takes_shows_a_loss(): void
    {
        // The whole reason the report exists. A comfortable-looking month on
        // one measure can still be a loss on the other.
        $subscription = $this->covered();
        $this->claim($subscription, 9000, 7000);

        $f = $this->figures();

        $this->assertSame(-2000.0, $f['cashResult']);
        $this->assertSame(-4000.0, $f['tradingResult']);
    }

    public function test_premiums_and_claims_outside_the_period_are_left_out(): void
    {
        $subscription = $this->covered();
        $this->claim($subscription, 4000, 2500);

        $f = $this->figures([
            'from' => now()->addMonth()->startOfMonth()->toDateString(),
            'to'   => now()->addMonth()->endOfMonth()->toDateString(),
        ]);

        $this->assertSame(0.0, $f['premiums']);
        $this->assertSame(0.0, $f['claimedCost']);
    }

    public function test_what_could_be_claimed_this_month_is_shown(): void
    {
        // Not a prediction - the ceiling the pharmacy has agreed to carry.
        $this->covered(['monthly_cover' => 10000]);
        $this->covered(['monthly_cover' => 10000]);

        $f = $this->figures();

        $this->assertSame(2, $f['liveCount']);
        $this->assertSame(20000.0, $f['exposure']);
    }

    public function test_each_plan_is_reported_on_its_own(): void
    {
        // So a plan that loses money can be repriced rather than the whole
        // scheme abandoned.
        $bronze = $this->covered(['name' => 'Bronze', 'monthly_premium' => 5000]);
        $silver = $this->covered(['name' => 'Silver', 'monthly_premium' => 9000, 'monthly_cover' => 20000]);

        $this->claim($bronze, 8000, 6000);   // loses on cash
        $this->claim($silver, 2000, 1200);   // comfortably ahead

        $rows = $this->figures()['byPlan'];

        $this->assertCount(2, $rows);

        $bronzeRow = collect($rows)->firstWhere('plan.name', 'Bronze');
        $silverRow = collect($rows)->firstWhere('plan.name', 'Silver');

        $this->assertLessThan(0, $bronzeRow['premiums'] - $bronzeRow['cost']);
        $this->assertGreaterThan(0, $silverRow['premiums'] - $silverRow['cost']);
    }

    public function test_the_heaviest_claimers_are_listed(): void
    {
        $light = $this->covered();
        $heavy = $this->covered();

        $this->claim($light, 1000, 700);
        $this->claim($heavy, 9000, 6000);

        $rows = $this->figures()['heaviest'];

        $this->assertSame($heavy->customer_id, $rows->first()->customer_id);
        $this->assertEquals(9000, $rows->first()->claimed);
    }

    public function test_a_month_with_nothing_in_it_reports_zero_rather_than_breaking(): void
    {
        $f = $this->figures();

        $this->assertSame(0.0, $f['premiums']);
        $this->assertSame(0.0, $f['cashResult']);
        $this->assertSame(0, $f['claimCount']);
    }

    // ── who may see it ──────────────────────────────────────────────────

    public function test_the_auditor_can_read_it(): void
    {
        $this->actingAs($this->user(['auditor']))
            ->get(route('insurance.report'))
            ->assertOk();
    }

    public function test_a_cashier_cannot(): void
    {
        // It carries margin, which is not a cashier's to see.
        $this->actingAs($this->user(['cashier']))
            ->get(route('insurance.report'))
            ->assertForbidden();
    }

    public function test_a_pharmacist_cannot(): void
    {
        $this->actingAs($this->user(['pharmacist']))
            ->get(route('insurance.report'))
            ->assertForbidden();
    }
}
