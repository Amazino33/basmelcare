<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * Registration, OTP and the Wi-Fi code are steps inside ONE dialog.
 *
 * They used to be three separate <x-modal>s. Every MaryUI modal carries
 * x-trap="open", so closing one and opening another in the same Livewire
 * response handed Alpine's focus trap over mid-tick: the OTP box appeared but
 * would not accept typing. Only one dialog may exist at a time.
 */
class PromoterFlowModalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $fake = Mockery::mock(WhatsAppService::class);
        $fake->shouldReceive('deliver')->andReturn(WhatsAppService::VIA_WHATSAPP);
        $fake->shouldReceive('send')->andReturn(true);
        $this->app->instance(WhatsAppService::class, $fake);
    }

    private function promoter(): User
    {
        return User::factory()->create([
            'role' => ['promoter'], 'status' => 'active', 'phone' => '08099990000',
        ]);
    }

    /**
     * The page also holds an independent "Add Medical Record" modal, which is
     * never open during this flow. So the flow itself must contribute exactly
     * one dialog, giving two in total. Splitting the flow back into separate
     * modals would push this to three or four — which is the bug.
     */
    private const DIALOGS_WHEN_FLOW_IS_ONE = 2;

    private function countDialogs(string $html): int
    {
        return substr_count($html, '<dialog');
    }

    public function test_the_flow_renders_only_one_dialog_at_each_step(): void
    {
        $this->actingAs($this->promoter());

        $component = Livewire::test(\App\Livewire\Customers\Index::class)
            ->call('create')
            ->set('name', 'Aisha Bello')
            ->set('type', 'retail')
            ->set('phone', '08031112233');

        $this->assertSame(self::DIALOGS_WHEN_FLOW_IS_ONE, $this->countDialogs($component->html()),
            'The form step should contribute exactly one dialog.');

        $component->call('save');
        $customer = Customer::where('phone', '08031112233')->firstOrFail();

        $this->assertSame(self::DIALOGS_WHEN_FLOW_IS_ONE, $this->countDialogs($component->html()),
            'Extra dialog at the OTP step — the focus trap bug is back.');

        $component->set('otpCode', $customer->fresh()->otp)->call('confirmOtp');

        $this->assertSame(self::DIALOGS_WHEN_FLOW_IS_ONE, $this->countDialogs($component->html()),
            'Extra dialog at the Wi-Fi code step — the focus trap bug is back.');
    }

    public function test_the_dialog_stays_open_across_the_whole_flow(): void
    {
        $this->actingAs($this->promoter());

        $component = Livewire::test(\App\Livewire\Customers\Index::class)
            ->call('create')
            ->set('name', 'Aisha Bello')
            ->set('type', 'retail')
            ->set('phone', '08031112233')
            ->call('save');

        // Closing and reopening is exactly what broke the focus trap.
        $component->assertSet('modal', true)->assertSet('otpModal', true);

        $customer = Customer::where('phone', '08031112233')->firstOrFail();
        $component->set('otpCode', $customer->fresh()->otp)->call('confirmOtp');

        $component->assertSet('modal', true)->assertSet('codeModal', true);
    }

    public function test_the_otp_input_is_present_and_bound(): void
    {
        $this->actingAs($this->promoter());

        $component = Livewire::test(\App\Livewire\Customers\Index::class)
            ->call('create')
            ->set('name', 'Aisha Bello')
            ->set('type', 'retail')
            ->set('phone', '08031112233')
            ->call('save');

        $html = $component->html();

        $this->assertStringContainsString('wire:model="otpCode"', $html);
        $this->assertStringContainsString('Enter OTP', $html);
        // The registration form must be gone, not merely hidden behind it.
        $this->assertStringNotContainsString('wire:model="notes"', $html);
    }

    public function test_typing_the_otp_reaches_the_component(): void
    {
        $this->actingAs($this->promoter());

        $component = Livewire::test(\App\Livewire\Customers\Index::class)
            ->call('create')
            ->set('name', 'Aisha Bello')
            ->set('type', 'retail')
            ->set('phone', '08031112233')
            ->call('save');

        $component->set('otpCode', '123456')->assertSet('otpCode', '123456');
    }

    public function test_a_wrong_otp_keeps_the_dialog_open_for_another_try(): void
    {
        $this->actingAs($this->promoter());

        $component = Livewire::test(\App\Livewire\Customers\Index::class)
            ->call('create')
            ->set('name', 'Aisha Bello')
            ->set('type', 'retail')
            ->set('phone', '08031112233')
            ->call('save')
            ->set('otpCode', '000000')
            ->call('confirmOtp');

        $component->assertSet('modal', true)->assertSet('otpModal', true);
        $this->assertNotSame('', $component->get('otpError'));
        $this->assertSame(self::DIALOGS_WHEN_FLOW_IS_ONE, $this->countDialogs($component->html()));
    }

    public function test_finishing_closes_the_dialog_completely(): void
    {
        $this->actingAs($this->promoter());

        $component = Livewire::test(\App\Livewire\Customers\Index::class)
            ->call('create')
            ->set('name', 'Aisha Bello')
            ->set('type', 'retail')
            ->set('phone', '08031112233')
            ->call('save');

        $customer = Customer::where('phone', '08031112233')->firstOrFail();

        $component->set('otpCode', $customer->fresh()->otp)
            ->call('confirmOtp')
            ->call('closeCodeModal');

        $component->assertSet('modal', false)
            ->assertSet('otpModal', false)
            ->assertSet('codeModal', false);
    }

    public function test_skipping_verification_closes_the_dialog(): void
    {
        $this->actingAs($this->promoter());

        Livewire::test(\App\Livewire\Customers\Index::class)
            ->call('create')
            ->set('name', 'Aisha Bello')
            ->set('type', 'retail')
            ->set('phone', '08031112233')
            ->call('save')
            ->call('skipOtp')
            ->assertSet('modal', false)
            ->assertSet('otpModal', false);
    }

    public function test_a_non_promoter_still_gets_a_plain_create_form(): void
    {
        $this->actingAs(User::factory()->create(['role' => ['cashier'], 'status' => 'active']));

        $component = Livewire::test(\App\Livewire\Customers\Index::class)
            ->call('create')
            ->set('name', 'Walk In')
            ->set('type', 'retail')
            ->call('save');

        // No OTP step for non-promoters; the dialog just closes.
        $component->assertSet('modal', false)->assertSet('otpModal', false);
        $this->assertSame(1, Customer::where('name', 'WALK IN')->count());
    }
}
