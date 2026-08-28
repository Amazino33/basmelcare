<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Signing in on the shop.
 *
 * Customers are not staff. They live on their own guard, sign in with a code
 * sent to their WhatsApp or with a password, and never reach the staff app -
 * which is a different application on a different subdomain.
 *
 * This file replaces the Breeze staff-auth tests that came across when the two
 * apps were split. Those described a /dashboard, a /register screen and a
 * layout.navigation component that do not exist here.
 */
class CustomerAuthTest extends TestCase
{
    use RefreshDatabase;

    private function customer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'name'  => 'ADAEZE OKON',
            'type'  => 'retail',
            'email' => 'adaeze@example.com',
            'phone' => '08031234567',
        ], $attributes));
    }

    // ── the guard sends people to the right door ────────────────────────

    public function test_a_signed_out_visitor_at_the_account_page_is_sent_to_sign_in(): void
    {
        // Laravel's default is route('login'), which this app does not define -
        // the shop's sign-in is customer.login. Without redirectGuestsTo, a
        // customer following an old bookmark met a 500.
        $this->get(route('customer.account'))
            ->assertRedirect(route('customer.login'));
    }

    public function test_a_signed_in_customer_reaches_their_account(): void
    {
        $this->actingAs($this->customer(), 'customer')
            ->get(route('customer.account'))
            ->assertOk();
    }

    public function test_the_sign_in_page_is_open_to_anyone(): void
    {
        $this->get(route('customer.login'))->assertOk();
        $this->get(route('customer.register'))->assertOk();
    }

    public function test_someone_already_signed_in_is_not_shown_the_sign_in_page(): void
    {
        $this->actingAs($this->customer(), 'customer')
            ->get(route('customer.login'))
            ->assertRedirect();
    }

    // ── signing in with a code ──────────────────────────────────────────

    public function test_a_code_is_only_sent_to_an_account_that_exists(): void
    {
        Livewire::test(\App\Livewire\Customer\Login::class)
            ->set('identifier', 'nobody@example.com')
            ->call('sendOtp')
            ->assertHasErrors('identifier');

        $this->assertGuest('customer');
    }

    public function test_asking_for_a_code_actually_sends_one(): void
    {
        // Both sign-in and sign-up built WhatsAppService by hand after it grew
        // an SMS fallback in its constructor, so every customer trying to reach
        // their account hit a fatal error before a code was ever sent.
        $customer = $this->customer();

        Livewire::test(\App\Livewire\Customer\Login::class)
            ->set('identifier', $customer->email)
            ->call('sendOtp')
            ->assertHasNoErrors()
            ->assertSet('otpSent', true);

        $this->assertNotNull($customer->fresh()->otp_code ?? $customer->fresh()->otp);
    }

    public function test_a_wrong_code_does_not_sign_anyone_in(): void
    {
        $customer = $this->customer();
        $customer->generateOtp();

        Livewire::test(\App\Livewire\Customer\Login::class)
            ->set('identifier', $customer->email)
            ->set('otp', '000000')
            ->call('verifyOtp')
            ->assertHasErrors('otp');

        $this->assertGuest('customer');
    }

    public function test_the_right_code_signs_them_in_and_is_then_spent(): void
    {
        $customer = $this->customer();
        $otp      = $customer->generateOtp();

        Livewire::test(\App\Livewire\Customer\Login::class)
            ->set('identifier', $customer->email)
            ->set('otp', $otp)
            ->call('verifyOtp')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($customer, 'customer');

        // A code that still worked afterwards would sit in the customer's chat
        // history as a reusable key to their order history.
        Auth::guard('customer')->logout();

        Livewire::test(\App\Livewire\Customer\Login::class)
            ->set('identifier', $customer->email)
            ->set('otp', $otp)
            ->call('verifyOtp')
            ->assertHasErrors('otp');
    }

    public function test_a_phone_number_works_as_well_as_an_email(): void
    {
        // People give their number, not their email, at the counter - so that
        // is what they will type here.
        $customer = $this->customer();
        $otp      = $customer->generateOtp();

        Livewire::test(\App\Livewire\Customer\Login::class)
            ->set('identifier', '08031234567')
            ->set('otp', $otp)
            ->call('verifyOtp')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($customer, 'customer');
    }

    // ── signing in with a password ──────────────────────────────────────

    public function test_a_wrong_password_is_refused(): void
    {
        $this->customer(['password' => Hash::make('correct-horse')]);

        Livewire::test(\App\Livewire\Customer\Login::class)
            ->set('usePassword', true)
            ->set('identifier', 'adaeze@example.com')
            ->set('password', 'not-it')
            ->call('loginWithPassword')
            ->assertHasErrors();

        $this->assertGuest('customer');
    }

    public function test_the_right_password_signs_them_in(): void
    {
        $customer = $this->customer(['password' => Hash::make('correct-horse')]);

        Livewire::test(\App\Livewire\Customer\Login::class)
            ->set('usePassword', true)
            ->set('identifier', 'adaeze@example.com')
            ->set('password', 'correct-horse')
            ->call('loginWithPassword')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($customer, 'customer');
    }

    // ── registering ─────────────────────────────────────────────────────

    public function test_a_new_customer_can_open_an_account(): void
    {
        Livewire::test(\App\Livewire\Customer\Register::class)
            ->set('name', 'Uche Nwosu')
            ->set('email', 'uche@example.com')
            ->set('phone', '08039998888')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register')
            ->assertHasNoErrors();

        $this->assertNotNull(Customer::where('email', 'uche@example.com')->first());
    }

    public function test_an_email_already_in_use_is_refused(): void
    {
        // Otherwise a second account shadows the first, and the customer's
        // purchase history splits in two without either of them knowing.
        $this->customer();

        Livewire::test(\App\Livewire\Customer\Register::class)
            ->set('name', 'Someone Else')
            ->set('email', 'adaeze@example.com')
            ->set('phone', '08037776666')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register')
            ->assertHasErrors('email');

        $this->assertSame(1, Customer::count());
    }

    public function test_a_mistyped_confirmation_stops_the_registration(): void
    {
        Livewire::test(\App\Livewire\Customer\Register::class)
            ->set('name', 'Uche Nwosu')
            ->set('email', 'uche@example.com')
            ->set('phone', '08039998888')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password124')
            ->call('register')
            ->assertHasErrors('password');

        $this->assertSame(0, Customer::count());
    }
}
