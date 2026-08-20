<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\PromoterCode;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * A coupon is added to the Wi-Fi welcome message only when a customer could
 * actually redeem it. Texting a dead or misrepresented code sends people to the
 * counter for a discount they cannot get, and the promoter takes the blame.
 */
class PromoterCouponMessageTest extends TestCase
{
    use RefreshDatabase;

    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        AppSetting::set('pharmacy_name', 'BasmelCare');
        AppSetting::set('voucher_validity_hours', 24);

        // Capture outgoing messages instead of sending them.
        $this->sent = [];
        $fake = Mockery::mock(WhatsAppService::class);

        // Delivered over WhatsApp, i.e. the customer has a smartphone and the
        // normal code-then-connect flow applies.
        $fake->shouldReceive('deliver')->andReturnUsing(function ($phone, $message) {
            $this->sent[] = $message;

            return WhatsAppService::VIA_WHATSAPP;
        });
        $fake->shouldReceive('send')->andReturnUsing(function ($phone, $message) {
            $this->sent[] = $message;

            return true;
        });
        $this->app->instance(WhatsAppService::class, $fake);
    }

    private function promoter(): User
    {
        return User::factory()->create([
            'role' => ['promoter'], 'status' => 'active', 'phone' => '08099990000',
        ]);
    }

    /** Runs a full promoter registration and returns the Wi-Fi code message. */
    private function registerAndCaptureWifiMessage(): string
    {
        $this->actingAs($this->promoter());

        $component = Livewire::test(\App\Livewire\Customers\Index::class)
            ->call('create')
            ->set('name', 'Aisha Bello')
            ->set('type', 'retail')
            ->set('phone', '08031112233')
            ->call('save');

        $customer = Customer::where('phone', '08031112233')->firstOrFail();

        $component->set('otpCode', $customer->fresh()->otp)->call('confirmOtp');

        $this->assertTrue(
            PromoterCode::where('customer_id', $customer->id)->exists(),
            'No Wi-Fi code was issued, so no message was sent.'
        );

        // [0] is the OTP, [1] is the Wi-Fi code message.
        return $this->sent[1] ?? '';
    }

    public function test_no_coupon_configured_leaves_the_message_unchanged(): void
    {
        AppSetting::set('promoter_coupon_code', '');

        $message = $this->registerAndCaptureWifiMessage();

        $this->assertStringContainsString('free Wi-Fi code', $message);
        $this->assertStringNotContainsString('Show this code', $message);
    }

    public function test_a_valid_coupon_is_added_with_its_conditions(): void
    {
        Coupon::create([
            'code' => 'WELCOME10', 'type' => 'percent', 'value' => 10,
            'is_active' => true, 'customer_type' => 'new',
            'min_order_amount' => 5000,
        ]);
        AppSetting::set('promoter_coupon_code', 'WELCOME10');

        $message = $this->registerAndCaptureWifiMessage();

        $this->assertStringContainsString('WELCOME10', $message);
        $this->assertStringContainsString('10% off', $message);
        // The customer must learn the conditions here, not at the till.
        $this->assertStringContainsString('First purchase only', $message);
        $this->assertStringContainsString('₦5,000', $message);
    }

    public function test_a_used_up_coupon_is_never_sent(): void
    {
        Coupon::create([
            'code' => 'SAVE500', 'type' => 'fixed', 'value' => 500,
            'is_active' => true, 'max_uses' => 2, 'used_count' => 2,
        ]);
        AppSetting::set('promoter_coupon_code', 'SAVE500');

        $message = $this->registerAndCaptureWifiMessage();

        $this->assertStringNotContainsString('SAVE500', $message);
    }

    public function test_an_expired_coupon_is_never_sent(): void
    {
        $coupon = Coupon::create([
            'code' => 'OLDPROMO', 'type' => 'fixed', 'value' => 200, 'is_active' => true,
        ]);
        $coupon->forceFill(['expires_at' => now()->subDay()])->save();
        AppSetting::set('promoter_coupon_code', 'OLDPROMO');

        $message = $this->registerAndCaptureWifiMessage();

        $this->assertStringNotContainsString('OLDPROMO', $message);
    }

    public function test_an_inactive_coupon_is_never_sent(): void
    {
        Coupon::create([
            'code' => 'PAUSED', 'type' => 'fixed', 'value' => 300, 'is_active' => false,
        ]);
        AppSetting::set('promoter_coupon_code', 'PAUSED');

        $this->assertStringNotContainsString('PAUSED', $this->registerAndCaptureWifiMessage());
    }

    public function test_an_auto_applying_coupon_is_never_sent(): void
    {
        // It applies at the till on its own; a code would only confuse.
        Coupon::create([
            'code' => 'AUTO5', 'type' => 'percent', 'value' => 5,
            'is_active' => true, 'auto_apply' => true,
        ]);
        AppSetting::set('promoter_coupon_code', 'AUTO5');

        $this->assertStringNotContainsString('AUTO5', $this->registerAndCaptureWifiMessage());
    }

    public function test_a_deleted_coupon_does_not_break_the_message(): void
    {
        AppSetting::set('promoter_coupon_code', 'GONE');

        $message = $this->registerAndCaptureWifiMessage();

        $this->assertStringContainsString('free Wi-Fi code', $message);
        $this->assertStringNotContainsString('GONE', $message);
    }

    public function test_an_unconditional_coupon_adds_no_conditions_text(): void
    {
        Coupon::create([
            'code' => 'FLAT200', 'type' => 'fixed', 'value' => 200, 'is_active' => true,
        ]);
        AppSetting::set('promoter_coupon_code', 'FLAT200');

        $message = $this->registerAndCaptureWifiMessage();

        $this->assertStringContainsString('₦200 off', $message);
        $this->assertStringEndsWith('*FLAT200*.', trim($message));
    }

    public function test_percentage_cap_is_stated(): void
    {
        Coupon::create([
            'code' => 'TEN', 'type' => 'percent', 'value' => 10,
            'max_discount' => 500, 'is_active' => true,
        ]);
        AppSetting::set('promoter_coupon_code', 'TEN');

        $this->assertStringContainsString('up to ₦500', $this->registerAndCaptureWifiMessage());
    }

    public function test_otp_message_stays_short_and_free_of_marketing(): void
    {
        Coupon::create([
            'code' => 'WELCOME10', 'type' => 'percent', 'value' => 10, 'is_active' => true,
        ]);
        AppSetting::set('promoter_coupon_code', 'WELCOME10');

        $this->registerAndCaptureWifiMessage();

        $otpMessage = $this->sent[0] ?? '';
        $this->assertStringContainsString('registration code', $otpMessage);
        $this->assertStringNotContainsString('WELCOME10', $otpMessage);
    }
}
