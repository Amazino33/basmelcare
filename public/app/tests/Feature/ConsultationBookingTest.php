<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\User;
use App\Support\ConsultationPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Appointments become paid consultations, booked by staff and paid at the
 * counter.
 *
 * The system records HOW a consultation happens - video, text, in person - so
 * it can be priced and prepared for. It does not host the call or the chat,
 * and is not pretending to: staff arrange those as they already do.
 *
 * The free allowance is configurable, and whether a consultation was free is
 * recorded rather than recalculated. The settings change; a consultation given
 * free last year was still given free.
 */
class ConsultationBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AppSetting::set('consult_free_count', 1);
        AppSetting::set('consult_free_period', 'ever');
        AppSetting::set(ConsultationPricing::priceKey('pharmacist', 'physical'), 1500);
        AppSetting::set(ConsultationPricing::priceKey('pharmacist', 'video'), 2500);
        AppSetting::set(ConsultationPricing::priceKey('pharmacist', 'text'), 1000);
    }

    private function staff(array $roles = ['admin']): User
    {
        return User::factory()->create(['role' => $roles, 'status' => 'active']);
    }

    private function customer(): Customer
    {
        return Customer::create([
            'name'  => 'ADA OKAFOR',
            'type'  => 'retail',
            'phone' => '080' . random_int(10000000, 99999999),
        ]);
    }

    private function book(Customer $customer, string $mode = 'physical', ?User $by = null): Appointment
    {
        Livewire::actingAs($by ?? $this->staff())
            ->test(\App\Livewire\Appointments\Index::class)
            ->set('customer_id', $customer->id)
            ->set('title', 'Medication review')
            ->set('scheduled_date', now()->addDay()->format('Y-m-d'))
            ->set('scheduled_time', '14:00')
            ->set('duration_minutes', 30)
            ->set('mode', $mode)
            ->set('provider_type', 'pharmacist')
            ->set('contact', $mode === 'physical' ? '' : '08031234567')
            ->call('save');

        return Appointment::latest('id')->firstOrFail();
    }

    // ── pricing ─────────────────────────────────────────────────────────

    public function test_the_price_depends_on_how_the_consultation_happens(): void
    {
        // A video call and a text exchange are not the same amount of anyone's
        // time, so they are not the same price.
        $this->assertSame(1500.0, ConsultationPricing::price('pharmacist', 'physical'));
        $this->assertSame(2500.0, ConsultationPricing::price('pharmacist', 'video'));
        $this->assertSame(1000.0, ConsultationPricing::price('pharmacist', 'text'));
    }

    public function test_the_first_consultation_is_free(): void
    {
        $appointment = $this->book($this->customer(), 'video');

        $this->assertTrue($appointment->was_free);
        $this->assertEquals(0, $appointment->price);
        $this->assertSame('free', $appointment->payment_status);
    }

    public function test_the_second_one_is_charged(): void
    {
        $customer = $this->customer();

        $this->book($customer, 'video');
        $second = $this->book($customer, 'video');

        $this->assertFalse($second->was_free);
        $this->assertEquals(2500, $second->price);
        $this->assertSame('pending', $second->payment_status);
    }

    public function test_the_free_allowance_is_configurable(): void
    {
        AppSetting::set('consult_free_count', 2);

        $customer = $this->customer();

        $this->assertTrue($this->book($customer)->was_free);
        $this->assertTrue($this->book($customer)->was_free);
        $this->assertFalse($this->book($customer)->was_free);
    }

    public function test_the_free_allowance_can_be_switched_off(): void
    {
        AppSetting::set('consult_free_period', 'none');

        $this->assertFalse($this->book($this->customer())->was_free);
    }

    public function test_a_yearly_allowance_resets(): void
    {
        AppSetting::set('consult_free_period', 'year');

        $customer = $this->customer();

        $used = $this->book($customer);
        $used->forceFill(['created_at' => now()->subYear()])->save();

        $this->assertTrue($this->book($customer)->was_free, 'Last year should not count against this year.');
    }

    public function test_a_cancelled_consultation_does_not_use_up_the_free_one(): void
    {
        $customer = $this->customer();

        $this->book($customer)->update(['status' => 'cancelled']);

        $this->assertTrue($this->book($customer)->was_free);
    }

    public function test_one_customers_free_one_is_their_own(): void
    {
        $this->book($this->customer());

        $this->assertTrue($this->book($this->customer())->was_free);
    }

    // ── what is recorded ────────────────────────────────────────────────

    public function test_the_mode_and_provider_are_recorded(): void
    {
        $appointment = $this->book($this->customer(), 'video');

        $this->assertSame('video', $appointment->mode);
        $this->assertSame('pharmacist', $appointment->provider_type);
        $this->assertSame('Video call', $appointment->modeLabel());
    }

    public function test_a_video_consultation_needs_a_contact(): void
    {
        // Staff arrange the call, so they need somewhere to reach the customer.
        $customer = $this->customer();

        Livewire::actingAs($this->staff())
            ->test(\App\Livewire\Appointments\Index::class)
            ->set('customer_id', $customer->id)
            ->set('title', 'Consult')
            ->set('scheduled_date', now()->addDay()->format('Y-m-d'))
            ->set('scheduled_time', '14:00')
            ->set('mode', 'video')
            ->set('contact', '')
            ->call('save')
            ->assertHasErrors('contact');
    }

    public function test_an_in_person_consultation_does_not(): void
    {
        $this->assertNotNull($this->book($this->customer(), 'physical')->id);
    }

    public function test_who_booked_it_is_recorded(): void
    {
        $staff = $this->staff();

        $this->assertSame($staff->id, $this->book($this->customer(), by: $staff)->booked_by);
    }

    // ── payment at the counter ──────────────────────────────────────────

    public function test_a_charged_consultation_can_be_marked_paid(): void
    {
        $customer = $this->customer();
        $this->book($customer);
        $second = $this->book($customer);

        Livewire::actingAs($this->staff())
            ->test(\App\Livewire\Appointments\Index::class)
            ->call('markPaid', $second->id);

        $second->refresh();

        $this->assertSame('paid', $second->payment_status);
        $this->assertNotNull($second->paid_at);
        $this->assertTrue($second->isSettled());
    }

    public function test_a_free_consultation_is_already_settled(): void
    {
        $this->assertTrue($this->book($this->customer())->isSettled());
    }

    public function test_it_cannot_be_paid_twice(): void
    {
        $customer = $this->customer();
        $this->book($customer);
        $second = $this->book($customer);

        $page = Livewire::actingAs($this->staff())->test(\App\Livewire\Appointments\Index::class);
        $page->call('markPaid', $second->id);
        $paidAt = $second->fresh()->paid_at;

        $page->call('markPaid', $second->id);

        $this->assertEquals($paidAt, $second->fresh()->paid_at);
    }

    // ── editing must not re-price ───────────────────────────────────────

    public function test_editing_does_not_take_away_a_free_consultation(): void
    {
        // Re-pricing on every edit would change what the customer was quoted,
        // and could withdraw a free one they had already been promised.
        $customer    = $this->customer();
        $appointment = $this->book($customer, 'video');

        $this->assertTrue($appointment->was_free);

        Livewire::actingAs($this->staff())
            ->test(\App\Livewire\Appointments\Index::class)
            ->call('edit', $appointment->id)
            ->set('title', 'Medication review (rescheduled)')
            ->call('save');

        $appointment->refresh();

        $this->assertTrue($appointment->was_free);
        $this->assertEquals(0, $appointment->price);
        $this->assertSame('Medication review (rescheduled)', $appointment->title);
    }

    public function test_editing_keeps_the_mode_and_contact(): void
    {
        $appointment = $this->book($this->customer(), 'video');

        Livewire::actingAs($this->staff())
            ->test(\App\Livewire\Appointments\Index::class)
            ->call('edit', $appointment->id)
            ->call('save');

        $appointment->refresh();

        $this->assertSame('video', $appointment->mode);
        $this->assertSame('08031234567', $appointment->contact);
    }

    // ── the settings screen ─────────────────────────────────────────────

    public function test_prices_and_the_allowance_can_be_changed(): void
    {
        Livewire::actingAs($this->staff())
            ->test(\App\Livewire\Settings\Index::class)
            ->set('consult_prices.pharmacist.video', '3000')
            ->set('consult_free_count', 0)
            ->set('consult_free_period', 'none')
            ->call('saveConsultations')
            ->assertHasNoErrors();

        $this->assertSame(3000.0, ConsultationPricing::price('pharmacist', 'video'));
        $this->assertFalse(ConsultationPricing::isFreeFor($this->customer()));
    }

    public function test_changing_the_rules_does_not_reprice_past_consultations(): void
    {
        // What was given free stays free.
        $appointment = $this->book($this->customer(), 'video');

        AppSetting::set('consult_free_period', 'none');
        AppSetting::set(ConsultationPricing::priceKey('pharmacist', 'video'), 9999);

        $appointment->refresh();

        $this->assertTrue($appointment->was_free);
        $this->assertEquals(0, $appointment->price);
    }
}
