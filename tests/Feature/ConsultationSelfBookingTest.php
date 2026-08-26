<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Support\ConsultationPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Booking a consultation from the shop.
 *
 * Identity comes from the phone number, matched to a customer or creating
 * one, because the free allowance is per customer - a booking with nobody
 * attached could claim the free one again and again.
 *
 * The customer asks for a time; staff confirm it or propose another. There is
 * no slot calendar, which would mean maintaining each provider's hours and
 * absences to answer a question a phone call already answers.
 */
class ConsultationSelfBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AppSetting::set('consult_free_count', 1);
        AppSetting::set('consult_free_period', 'ever');
        AppSetting::set('consult_hold_minutes', 60);
        AppSetting::set(ConsultationPricing::priceKey('pharmacist', 'physical'), 1500);
        AppSetting::set(ConsultationPricing::priceKey('pharmacist', 'video'), 2500);
    }

    private function form(string $phone = '08031234567', string $mode = 'physical')
    {
        return Livewire::test(\App\Livewire\Consultations\Book::class)
            ->set('name', 'Ada Okafor')
            ->set('phone', $phone)
            ->set('mode', $mode)
            ->set('contact', $mode === 'physical' ? '' : '08031234567')
            ->set('preferred_date', now()->addDay()->format('Y-m-d'))
            ->set('preferred_time', '14:00');
    }

    // ── identity ────────────────────────────────────────────────────────

    public function test_booking_creates_a_customer_from_the_phone_number(): void
    {
        $this->form()->call('book');

        $this->assertDatabaseHas('customers', ['phone' => '08031234567', 'name' => 'ADA OKAFOR']);
    }

    public function test_booking_matches_an_existing_customer(): void
    {
        $existing = Customer::create(['name' => 'ADA OKAFOR', 'type' => 'retail', 'phone' => '08031234567']);

        $this->form()->call('book');

        $this->assertSame(1, Customer::count(), 'A duplicate customer was created.');
        $this->assertSame($existing->id, Appointment::first()->customer_id);
    }

    public function test_the_free_one_cannot_be_claimed_twice_from_the_same_number(): void
    {
        // The reason identity matters at all here.
        $this->form()->call('book');
        $this->form()->call('book');

        $appointments = Appointment::orderBy('id')->get();

        $this->assertTrue($appointments[0]->was_free);
        $this->assertFalse($appointments[1]->was_free);
    }

    // ── what gets recorded ──────────────────────────────────────────────

    public function test_a_booking_is_a_request_not_a_confirmed_appointment(): void
    {
        // Staff agree the time; the customer only asks for one.
        $this->form()->call('book');

        $this->assertSame('requested', Appointment::first()->status);
    }

    public function test_a_self_booking_records_no_staff_member(): void
    {
        $this->form()->call('book');

        $this->assertTrue(Appointment::first()->isSelfBooked());
    }

    public function test_a_free_booking_is_settled_without_payment(): void
    {
        $this->form()->call('book');

        $appointment = Appointment::first();

        $this->assertSame('free', $appointment->payment_status);
        $this->assertTrue($appointment->isSettled());
    }

    public function test_a_charged_booking_goes_to_payment(): void
    {
        Customer::create(['name' => 'ADA', 'type' => 'retail', 'phone' => '08031234567']);
        AppSetting::set('consult_free_period', 'none');

        $this->form(mode: 'video')
            ->call('book')
            ->assertRedirect(route('consultation.pay', Appointment::first()));

        $appointment = Appointment::first();

        $this->assertSame('pending', $appointment->payment_status);
        $this->assertEquals(2500, $appointment->price);
    }

    // ── validation ──────────────────────────────────────────────────────

    public function test_a_video_booking_needs_a_contact(): void
    {
        Livewire::test(\App\Livewire\Consultations\Book::class)
            ->set('name', 'Ada')
            ->set('phone', '08031234567')
            ->set('mode', 'video')
            ->set('contact', '')
            ->set('preferred_date', now()->addDay()->format('Y-m-d'))
            ->set('preferred_time', '14:00')
            ->call('book')
            ->assertHasErrors('contact');
    }

    public function test_a_date_in_the_past_is_rejected(): void
    {
        $this->form()
            ->set('preferred_date', now()->subWeek()->format('Y-m-d'))
            ->call('book')
            ->assertHasErrors('preferred_date');
    }

    // ── unpaid bookings lapse ───────────────────────────────────────────

    private function unpaidBooking(): Appointment
    {
        AppSetting::set('consult_free_period', 'none');
        $this->form(mode: 'video')->call('book');

        return Appointment::first();
    }

    public function test_an_unpaid_booking_is_live_at_first(): void
    {
        $this->assertFalse(ConsultationPricing::hasLapsed($this->unpaidBooking()));
    }

    public function test_an_unpaid_booking_lapses_after_the_hold_window(): void
    {
        $appointment = $this->unpaidBooking();
        $appointment->forceFill(['created_at' => now()->subHours(2)])->save();

        $this->assertTrue(ConsultationPricing::hasLapsed($appointment->fresh()));
    }

    public function test_the_hold_window_is_configurable(): void
    {
        AppSetting::set('consult_hold_minutes', 5);

        $appointment = $this->unpaidBooking();
        $appointment->forceFill(['created_at' => now()->subMinutes(10)])->save();

        $this->assertTrue(ConsultationPricing::hasLapsed($appointment->fresh()));
    }

    public function test_a_paid_booking_never_lapses(): void
    {
        $appointment = $this->unpaidBooking();
        $appointment->forceFill(['created_at' => now()->subDays(3), 'payment_status' => 'paid'])->save();

        $this->assertFalse(ConsultationPricing::hasLapsed($appointment->fresh()));
    }

    public function test_a_staff_booking_never_lapses(): void
    {
        // Only abandoned online checkouts lapse. An unpaid consultation booked
        // at the counter is money owed, not a dead request.
        $staff = \App\Models\User::factory()->create();

        $appointment = $this->unpaidBooking();
        $appointment->forceFill(['created_at' => now()->subDays(3), 'booked_by' => $staff->id])->save();

        $this->assertFalse(ConsultationPricing::hasLapsed($appointment->fresh()));
    }

    public function test_paying_for_a_lapsed_booking_is_refused(): void
    {
        // Taking the money would promise something staff are no longer chasing.
        $appointment = $this->unpaidBooking();
        $appointment->forceFill(['created_at' => now()->subDay()])->save();

        $this->get(route('consultation.pay', $appointment))
            ->assertRedirect(route('consultation.book'));
    }

    public function test_an_already_paid_booking_goes_straight_to_the_confirmation(): void
    {
        $appointment = $this->unpaidBooking();
        $appointment->update(['payment_status' => 'paid']);

        $this->get(route('consultation.pay', $appointment))
            ->assertRedirect(route('consultation.confirmation', $appointment));
    }

    // ── the page ────────────────────────────────────────────────────────

    public function test_the_booking_page_loads(): void
    {
        $this->get(route('consultation.book'))->assertOk()->assertSee('Book a consultation');
    }

    public function test_the_price_is_only_shown_once_we_know_who_it_is(): void
    {
        // The free allowance is per customer, so it cannot be quoted before
        // there is a phone number to match on.
        \App\Models\Customer::create(['name' => 'ADA', 'type' => 'retail', 'phone' => '08031234567']);

        Livewire::test(\App\Livewire\Consultations\Book::class)
            ->assertDontSee('free consultation')
            ->set('phone', '08031234567')
            ->assertSee('No charge');
    }
}
