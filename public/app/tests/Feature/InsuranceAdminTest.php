<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InsurancePlan;
use App\Models\InsurancePremium;
use App\Models\InsuranceSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Setting cover up and running it.
 *
 * Two separate permissions on purpose: what a plan promises is a pricing
 * decision, while signing a customer up and taking their premium happens at
 * the counter. The auditor reads both and writes neither.
 */
class InsuranceAdminTest extends TestCase
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

    private function customer(): Customer
    {
        return Customer::create([
            'name'  => 'ADAEZE OKON',
            'type'  => 'retail',
            'phone' => '080' . random_int(10000000, 99999999),
        ]);
    }

    private function plan(array $overrides = []): InsurancePlan
    {
        return InsurancePlan::create(array_merge([
            'name' => 'Bronze', 'code' => 'BRONZE',
            'monthly_premium' => 5000, 'monthly_cover' => 10000,
            'copay_percent' => 0, 'waiting_days' => 30, 'grace_days' => 7,
            'is_active' => true,
        ], $overrides));
    }

    private function plansPage(?User $as = null)
    {
        return Livewire::actingAs($as ?? $this->user(['admin']))
            ->test(\App\Livewire\Insurance\Plans::class);
    }

    private function coverPage(?User $as = null)
    {
        return Livewire::actingAs($as ?? $this->user(['cashier']))
            ->test(\App\Livewire\Insurance\Subscriptions::class);
    }

    // ── setting up a plan ───────────────────────────────────────────────

    public function test_a_plan_can_be_created(): void
    {
        $this->plansPage()
            ->call('createPlan')
            ->set('name', 'Bronze')
            ->set('code', 'bronze')
            ->set('monthly_premium', 5000)
            ->set('monthly_cover', 10000)
            ->call('savePlan')
            ->assertHasNoErrors();

        $this->assertSame('BRONZE', InsurancePlan::sole()->code, 'The code was not normalised.');
    }

    public function test_a_plan_must_have_a_ceiling(): void
    {
        // A plan with no ceiling is an open promise against stock the pharmacy
        // has already paid for.
        $this->plansPage()
            ->call('createPlan')
            ->set('name', 'Unlimited')
            ->set('code', 'UNLIM')
            ->set('monthly_premium', 5000)
            ->set('monthly_cover', 0)
            ->call('savePlan')
            ->assertHasErrors('monthly_cover');

        $this->assertSame(0, InsurancePlan::count());
    }

    public function test_two_plans_cannot_share_a_code(): void
    {
        $this->plan();

        $this->plansPage()
            ->call('createPlan')
            ->set('name', 'Bronze Again')
            ->set('code', 'BRONZE')
            ->set('monthly_premium', 6000)
            ->set('monthly_cover', 9000)
            ->call('savePlan')
            ->assertHasErrors('code');
    }

    public function test_a_plan_is_withdrawn_rather_than_deleted(): void
    {
        // Subscriptions read their cover and waiting period off the plan, and
        // claims already paid under it have to stay explicable.
        $plan = $this->plan();

        $this->plansPage()->call('toggleActive', $plan->id);

        $this->assertFalse($plan->fresh()->is_active);
        $this->assertDatabaseHas('insurance_plans', ['id' => $plan->id]);
    }

    public function test_a_cashier_cannot_change_what_a_plan_promises(): void
    {
        $plan = $this->plan();

        $this->plansPage($this->user(['cashier']))
            ->call('editPlan', $plan->id)
            ->set('monthly_cover', 999999)
            ->call('savePlan');

        $this->assertEquals(10000, $plan->fresh()->monthly_cover);
    }

    public function test_an_auditor_cannot_change_a_plan(): void
    {
        $plan = $this->plan();

        $this->plansPage($this->user(['auditor']))
            ->call('editPlan', $plan->id)
            ->set('monthly_premium', 1)
            ->call('savePlan');

        $this->assertEquals(5000, $plan->fresh()->monthly_premium);
    }

    public function test_excluded_categories_are_kept_on_the_plan(): void
    {
        $cosmetics = Category::create(['name' => 'COSMETICS']);

        $this->plansPage()
            ->call('createPlan')
            ->set('name', 'Bronze')
            ->set('code', 'BRONZE')
            ->set('monthly_premium', 5000)
            ->set('monthly_cover', 10000)
            ->set('excluded_categories', [$cosmetics->id])
            ->call('savePlan')
            ->assertHasNoErrors();

        $this->assertSame([$cosmetics->id], InsurancePlan::sole()->excluded_categories);
    }

    // ── signing customers up ────────────────────────────────────────────

    public function test_a_cashier_can_sign_a_customer_up_and_take_the_premium(): void
    {
        $customer = $this->customer();
        $plan     = $this->plan();

        $this->coverPage()
            ->call('openSignUp')
            ->set('newCustomerId', $customer->id)
            ->set('newPlanId', $plan->id)
            ->set('collectFirstPremium', true)
            ->call('signUp')
            ->assertHasNoErrors();

        $subscription = InsuranceSubscription::sole();

        $this->assertSame(InsuranceSubscription::WAITING, $subscription->status);
        $this->assertEquals(5000, InsurancePremium::sole()->amount);
    }

    public function test_signing_up_without_paying_leaves_them_uncovered(): void
    {
        $customer = $this->customer();

        $this->coverPage()
            ->call('openSignUp')
            ->set('newCustomerId', $customer->id)
            ->set('newPlanId', $this->plan()->id)
            ->set('collectFirstPremium', false)
            ->call('signUp');

        $subscription = InsuranceSubscription::sole();

        $this->assertSame(InsuranceSubscription::PENDING, $subscription->status);
        $this->assertFalse($subscription->isClaimable());
    }

    public function test_a_customer_cannot_be_put_on_two_plans_at_once(): void
    {
        // Two live subscriptions would each carry their own cover, so one
        // customer's premiums would buy the cover twice over.
        $customer = $this->customer();
        $bronze   = $this->plan();
        $silver   = $this->plan(['name' => 'Silver', 'code' => 'SILVER']);

        $page = $this->coverPage();

        $page->call('openSignUp')
            ->set('newCustomerId', $customer->id)
            ->set('newPlanId', $bronze->id)
            ->call('signUp');

        $page->call('openSignUp')
            ->set('newCustomerId', $customer->id)
            ->set('newPlanId', $silver->id)
            ->call('signUp')
            ->assertHasErrors('newCustomerId');

        $this->assertSame(1, InsuranceSubscription::count());
    }

    public function test_a_cancelled_customer_can_be_signed_up_again(): void
    {
        $customer = $this->customer();
        $page     = $this->coverPage();

        $page->call('openSignUp')
            ->set('newCustomerId', $customer->id)
            ->set('newPlanId', $this->plan()->id)
            ->call('signUp');

        InsuranceSubscription::sole()->cancel('Changed their mind.');

        $page->call('openSignUp')
            ->set('newCustomerId', $customer->id)
            ->set('newPlanId', $this->plan(['name' => 'Silver', 'code' => 'SILVER'])->id)
            ->call('signUp')
            ->assertHasNoErrors();

        $this->assertSame(2, InsuranceSubscription::count());
    }

    // ── the monthly premium ─────────────────────────────────────────────

    public function test_recording_a_premium_extends_the_cover(): void
    {
        $customer = $this->customer();
        $page     = $this->coverPage();

        $page->call('openSignUp')
            ->set('newCustomerId', $customer->id)
            ->set('newPlanId', $this->plan(['waiting_days' => 0])->id)
            ->call('signUp');

        $subscription = InsuranceSubscription::sole();
        $firstEnd     = $subscription->period_end->copy();

        $page->call('openPremium', $subscription->id)
            ->call('recordPremium')
            ->assertHasNoErrors();

        $fresh = $subscription->fresh();

        // Asserted as "the next month runs on from the last one", not as
        // "the old end plus a month". Those are the same only while months are
        // the same length: paying on 31 August ends the first period on 30
        // September, and the month bought from 1 October ends on the 31st, not
        // the 30th. The shortcut failed on month ends and the code was right.
        $this->assertSame(
            $firstEnd->copy()->addDay()->toDateString(),
            $fresh->period_start->toDateString(),
            'The new month did not start the day after the old one ended.'
        );

        $this->assertSame(
            $fresh->period_start->copy()->addMonth()->subDay()->toDateString(),
            $fresh->period_end->toDateString(),
            'The customer was not given a full month.'
        );
    }

    public function test_a_premium_cannot_be_recorded_against_cancelled_cover(): void
    {
        $customer = $this->customer();
        $page     = $this->coverPage();

        $page->call('openSignUp')
            ->set('newCustomerId', $customer->id)
            ->set('newPlanId', $this->plan()->id)
            ->call('signUp');

        $subscription = InsuranceSubscription::sole();
        $subscription->cancel();

        $page->call('openPremium', $subscription->id)->call('recordPremium');

        $this->assertSame(1, InsurancePremium::count(), 'A premium was taken for cover that had ended.');
    }

    public function test_cancelling_stops_the_cover_immediately(): void
    {
        $customer = $this->customer();
        $page     = $this->coverPage();

        $page->call('openSignUp')
            ->set('newCustomerId', $customer->id)
            ->set('newPlanId', $this->plan(['waiting_days' => 0])->id)
            ->call('signUp');

        $subscription = InsuranceSubscription::sole();
        $this->assertTrue($subscription->isClaimable());

        $page->call('openCancel', $subscription->id)
            ->set('cancelReason', 'Customer asked to stop.')
            ->call('cancelCover');

        $this->assertFalse($subscription->fresh()->isClaimable());
    }

    // ── who may do what ─────────────────────────────────────────────────

    public function test_an_auditor_can_read_the_cover_page_but_not_sign_anyone_up(): void
    {
        $auditor = $this->user(['auditor']);

        $this->actingAs($auditor)->get(route('insurance.subscriptions'))->assertOk();

        $this->coverPage($auditor)
            ->call('openSignUp')
            ->set('newCustomerId', $this->customer()->id)
            ->set('newPlanId', $this->plan()->id)
            ->call('signUp');

        $this->assertSame(0, InsuranceSubscription::count());
    }

    public function test_an_auditor_cannot_take_a_premium(): void
    {
        // The auditor checks the money; they do not handle it.
        $customer = $this->customer();
        $this->coverPage()
            ->call('openSignUp')
            ->set('newCustomerId', $customer->id)
            ->set('newPlanId', $this->plan()->id)
            ->call('signUp');

        $subscription = InsuranceSubscription::sole();

        $this->coverPage($this->user(['auditor']))
            ->call('openPremium', $subscription->id)
            ->call('recordPremium');

        $this->assertSame(1, InsurancePremium::count());
    }

    public function test_a_pharmacist_has_no_business_on_either_page(): void
    {
        $pharmacist = $this->user(['pharmacist']);

        $this->actingAs($pharmacist)->get(route('insurance.subscriptions'))->assertForbidden();
        $this->actingAs($pharmacist)->get(route('insurance.plans'))->assertForbidden();
    }

    public function test_a_cashier_cannot_reach_the_plans_page(): void
    {
        $this->actingAs($this->user(['cashier']))
            ->get(route('insurance.plans'))
            ->assertForbidden();
    }

    // ── the switch ──────────────────────────────────────────────────────

    public function test_the_pages_still_work_while_the_scheme_is_off(): void
    {
        // The plans have to be set up before it can be turned on.
        AppSetting::set('insurance_enabled', '0');

        $this->actingAs($this->user(['admin']))->get(route('insurance.plans'))->assertOk();
        $this->actingAs($this->user(['admin']))->get(route('insurance.subscriptions'))->assertOk();
    }

    public function test_the_menu_hides_cover_until_it_is_switched_on(): void
    {
        AppSetting::set('insurance_enabled', '0');

        $html = $this->actingAs($this->user(['cashier']))->get(route('dashboard'))->getContent();

        $this->assertStringNotContainsString(route('insurance.subscriptions'), $html);
    }

    public function test_the_menu_shows_cover_once_it_is_on(): void
    {
        $html = $this->actingAs($this->user(['cashier']))->get(route('dashboard'))->getContent();

        $this->assertStringContainsString(route('insurance.subscriptions'), $html);
    }

    public function test_the_toggle_saves(): void
    {
        AppSetting::set('insurance_enabled', '0');

        Livewire::actingAs($this->user(['admin']))
            ->test(\App\Livewire\Settings\Index::class)
            ->set('insurance_enabled', true)
            ->call('saveInsurance');

        $this->assertTrue(AppSetting::bool('insurance_enabled'));
    }
}
