<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\PromoterCode;
use App\Models\ReferralCommission;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * A customer reachable only by SMS has no WhatsApp, so no device that can use
 * the Wi-Fi. They get no code, and the promoter is paid anyway rather than
 * being penalised for the customer's handset.
 *
 * The distinction that matters: WhatsApp being DOWN must not be mistaken for
 * the customer having no smartphone, or an outage would pay commission for
 * every registration without anybody connecting.
 */
class NoSmartDeviceTest extends TestCase
{
    use RefreshDatabase;

    private array $sent = [];

    /** Force every delivery to report the given channel. */
    private function deliverAs(string $channel): void
    {
        $this->sent = [];

        $fake = Mockery::mock(WhatsAppService::class);
        $fake->shouldReceive('deliver')->andReturnUsing(function ($phone, $message) use ($channel) {
            $this->sent[] = $message;

            return $channel;
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

    /** Registers a customer through the real component and returns [component, customer]. */
    private function register(User $promoter, string $phone = '08031112233'): array
    {
        $this->actingAs($promoter);

        $component = Livewire::test(\App\Livewire\Customers\Index::class)
            ->call('create')
            ->set('name', 'Aisha Bello')
            ->set('type', 'retail')
            ->set('phone', $phone)
            ->call('save');

        $customer = Customer::where('phone', $phone)->firstOrFail();
        $component->set('otpCode', $customer->fresh()->otp)->call('confirmOtp');

        return [$component, $customer->fresh()];
    }

    // ── SMS: no smart device ─────────────────────────────────────────

    public function test_sms_customer_gets_no_wifi_code_but_promoter_is_paid(): void
    {
        $this->deliverAs(WhatsAppService::VIA_SMS);
        $promoter = $this->promoter();

        [$component, $customer] = $this->register($promoter);

        $code = PromoterCode::where('customer_id', $customer->id)->first();
        $this->assertNotNull($code);
        $this->assertSame(WhatsAppService::VIA_SMS, $code->delivered_via);
        $this->assertNotNull($code->revoked_at, 'An unusable code should not be left live.');

        $commission = ReferralCommission::where('customer_id', $customer->id)->first();
        $this->assertNotNull($commission, 'Promoter was not paid for a no-device customer.');
        $this->assertEquals(
            (float) AppSetting::get('commission_amount', 100),
            (float) $commission->amount
        );

        $component->assertSet('noSmartDevice', true)->assertSet('codeRedeemed', true);
    }

    public function test_the_wifi_code_is_not_texted_to_a_feature_phone(): void
    {
        $this->deliverAs(WhatsAppService::VIA_SMS);
        [, $customer] = $this->register($this->promoter());

        $code    = PromoterCode::where('customer_id', $customer->id)->first();
        $welcome = end($this->sent);

        $this->assertStringNotContainsString($code->code, $welcome,
            'A Wi-Fi code was sent to a phone that cannot use it.');
        $this->assertStringNotContainsString('Wi-Fi code', $welcome);
    }

    public function test_the_coupon_still_reaches_a_feature_phone(): void
    {
        Coupon::create([
            'code' => 'FLAT200', 'type' => 'fixed', 'value' => 200, 'is_active' => true,
        ]);
        AppSetting::set('promoter_coupon_code', 'FLAT200');

        $this->deliverAs(WhatsAppService::VIA_SMS);
        $this->register($this->promoter());

        // A coupon works at the counter regardless of handset.
        $this->assertStringContainsString('FLAT200', end($this->sent));
    }

    public function test_a_no_device_customer_counts_towards_the_target(): void
    {
        $this->deliverAs(WhatsAppService::VIA_SMS);
        $promoter = $this->promoter();
        $this->register($promoter);

        $progress = $promoter->fresh()->promoterProgressOn(today());

        $this->assertSame(1, $progress['redeemed'], 'Paid customer did not count towards the target.');
        $this->assertSame(1, $progress['noDevice']);
        $this->assertSame(0, $progress['connected']);
        $this->assertSame(0, $progress['stalled'], 'A paid customer must not show as outstanding.');
    }

    // ── WhatsApp: normal flow unchanged ──────────────────────────────

    public function test_whatsapp_customer_still_must_connect_before_earning(): void
    {
        $this->deliverAs(WhatsAppService::VIA_WHATSAPP);
        $promoter = $this->promoter();

        [$component, $customer] = $this->register($promoter);

        $code = PromoterCode::where('customer_id', $customer->id)->first();
        $this->assertNull($code->revoked_at);
        $this->assertNull(ReferralCommission::where('customer_id', $customer->id)->first(),
            'Commission was paid before the customer connected.');

        $component->assertSet('noSmartDevice', false)->assertSet('codeRedeemed', false);
        $this->assertStringContainsString($code->code, end($this->sent));
    }

    // ── The dangerous case ───────────────────────────────────────────

    public function test_a_whatsapp_outage_is_not_mistaken_for_a_feature_phone(): void
    {
        // WhatsApp unavailable: we learned nothing about this customer.
        $this->deliverAs(WhatsAppService::VIA_SMS_DEGRADED);
        $promoter = $this->promoter();

        [$component, $customer] = $this->register($promoter);

        $this->assertNull(
            ReferralCommission::where('customer_id', $customer->id)->first(),
            'A WhatsApp outage paid commission without the customer connecting.'
        );

        $code = PromoterCode::where('customer_id', $customer->id)->first();
        $this->assertNull($code->revoked_at, 'The code should stay usable — they may well have a smartphone.');
        $component->assertSet('noSmartDevice', false);

        // And the code is still sent, so they can connect normally.
        $this->assertStringContainsString($code->code, end($this->sent));
    }

    public function test_unconfigured_whatsapp_reports_degraded_not_sms(): void
    {
        AppSetting::set('wawp_enabled', '0');

        $sms = Mockery::mock(\App\Services\KudiSmsService::class);
        $sms->shouldReceive('send')->andReturn(true);

        $service = new WhatsAppService($sms);

        $this->assertSame(
            WhatsAppService::VIA_SMS_DEGRADED,
            $service->deliver('08031112233', 'test'),
            'Unconfigured WhatsApp must not look like a customer without a smartphone.'
        );
    }
}
