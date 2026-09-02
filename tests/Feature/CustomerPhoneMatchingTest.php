<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * One person, one customer, however they write their number.
 *
 * Numbers arrive as 08031234567, 0803 123 4567, +2348031234567 and
 * 234 803 123 4567, and they are all the same line. Comparing the raw strings
 * made those four different customers - four purchase histories, four debts,
 * and four free consultations for one person.
 *
 * It matters most on an outreach, where the free visit is per customer and
 * nearly everybody is new.
 */
class CustomerPhoneMatchingTest extends TestCase
{
    use RefreshDatabase;

    private function customer(string $phone): Customer
    {
        return Customer::create(['name' => 'ADAEZE OKON', 'type' => 'retail', 'phone' => $phone]);
    }

    public static function sameNumberWrittenFourWays(): array
    {
        return [
            'plain'            => ['08031234567'],
            'with spaces'      => ['0803 123 4567'],
            'country code'     => ['2348031234567'],
            'international'    => ['+234 803 123 4567'],
            'dashes'           => ['0803-123-4567'],
            'no leading zero'  => ['8031234567'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('sameNumberWrittenFourWays')]
    public function test_every_spelling_finds_the_same_person(string $written): void
    {
        $customer = $this->customer('08031234567');

        $this->assertSame($customer->id, Customer::findByPhone($written)?->id,
            "\"{$written}\" did not find the customer.");
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('sameNumberWrittenFourWays')]
    public function test_a_customer_registered_any_way_is_still_found(string $written): void
    {
        // The other direction: it is the stored number that was typed oddly.
        $customer = $this->customer($written);

        $this->assertSame($customer->id, Customer::findByPhone('08031234567')?->id);
    }

    public function test_a_different_number_is_a_different_person(): void
    {
        $this->customer('08031234567');

        $this->assertNull(Customer::findByPhone('08039998888'));
    }

    public function test_something_with_no_digits_matches_nobody(): void
    {
        // Otherwise every customer with a blank number would be the same one.
        Customer::create(['name' => 'NO PHONE', 'type' => 'retail', 'phone' => null]);
        Customer::create(['name' => 'ALSO NONE', 'type' => 'retail', 'phone' => '']);

        $this->assertNull(Customer::findByPhone(''));
        $this->assertNull(Customer::findByPhone('n/a'));
        $this->assertNull(Customer::findByPhone(null));
    }

    public function test_the_number_is_kept_as_it_was_typed(): void
    {
        // The comparable form is for finding people. What staff read and what
        // gets printed is what the customer actually gave.
        $customer = $this->customer('+234 803 123 4567');

        $this->assertSame('+234 803 123 4567', $customer->fresh()->phone);
        $this->assertSame('8031234567', $customer->fresh()->phone_normalised);
    }

    public function test_changing_the_number_keeps_the_two_in_step(): void
    {
        $customer = $this->customer('08031234567');

        $customer->update(['phone' => '0709 888 7766']);

        $this->assertSame('7098887766', $customer->fresh()->phone_normalised);
        $this->assertSame($customer->id, Customer::findByPhone('+2347098887766')?->id);
    }

    // ── the flows that were creating the duplicates ─────────────────────

    public function test_booking_a_consultation_does_not_create_a_second_customer(): void
    {
        $customer = $this->customer('08031234567');

        Livewire::test(\App\Livewire\Consultations\Book::class)
            ->set('name', 'Adaeze Okon')
            ->set('phone', '+234 803 123 4567')
            ->set('mode', 'physical')
            ->set('preferred_date', now()->addDay()->toDateString())
            ->set('preferred_time', '10:00')
            ->call('book');

        $this->assertSame(1, Customer::count(), 'The same person was registered twice.');
        $this->assertSame($customer->id, \App\Models\Appointment::sole()->customer_id);
    }

    public function test_the_free_visit_cannot_be_had_twice_under_two_spellings(): void
    {
        // The reason this matters for the outreach.
        AppSetting::set('consult_free_count', 1);
        AppSetting::set('consult_free_period', 'month');

        $customer = $this->customer('08031234567');

        $this->assertTrue(\App\Support\ConsultationPricing::isFreeFor($customer));

        Livewire::test(\App\Livewire\Consultations\Book::class)
            ->set('name', 'Adaeze Okon')
            ->set('phone', '0803 123 4567')
            ->set('mode', 'physical')
            ->set('preferred_date', now()->addDay()->toDateString())
            ->set('preferred_time', '10:00')
            ->call('book');

        $this->assertFalse(
            \App\Support\ConsultationPricing::isFreeFor($customer->fresh()),
            'A second spelling of the number would have bought another free consultation.'
        );
    }

    public function test_signing_in_works_with_any_spelling(): void
    {
        $customer = $this->customer('08031234567');
        $customer->update(['password' => Hash::make('correct-horse')]);

        Livewire::test(\App\Livewire\Customer\Login::class)
            ->set('usePassword', true)
            ->set('identifier', '+2348031234567')
            ->set('password', 'correct-horse')
            ->call('loginWithPassword')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($customer->fresh(), 'customer');
    }

    public function test_signing_in_by_email_still_works(): void
    {
        $customer = $this->customer('08031234567');
        $customer->update(['email' => 'adaeze@example.com', 'password' => Hash::make('correct-horse')]);

        Livewire::test(\App\Livewire\Customer\Login::class)
            ->set('usePassword', true)
            ->set('identifier', 'adaeze@example.com')
            ->set('password', 'correct-horse')
            ->call('loginWithPassword')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($customer->fresh(), 'customer');
    }
}
