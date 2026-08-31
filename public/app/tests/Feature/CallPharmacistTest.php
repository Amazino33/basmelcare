<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Branch;
use App\Models\PharmacistCall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The counter calling for a pharmacist.
 *
 * One tap, no form: at a busy counter speed is the point, and a pharmacist
 * walking over finds out what is needed by asking.
 *
 * Polled rather than pushed. There is no realtime infrastructure here, and the
 * till and cashier screens already refresh this way - websockets for one
 * button would be a lot of moving parts to save five seconds.
 */
class CallPharmacistTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $roles, ?int $branchId = null): User
    {
        return User::factory()->create([
            'role'      => $roles,
            'status'    => 'active',
            'branch_id' => $branchId,
        ]);
    }

    private function bar(User $as)
    {
        return Livewire::actingAs($as)->test(\App\Livewire\CallPharmacist::class);
    }

    // ── ringing ─────────────────────────────────────────────────────────

    public function test_a_sales_person_can_call(): void
    {
        $this->bar($this->user(['sales']))->call('callPharmacist');

        $this->assertSame(1, PharmacistCall::count());
    }

    public function test_a_cashier_can_call(): void
    {
        $this->bar($this->user(['cashier']))->call('callPharmacist');

        $this->assertSame(1, PharmacistCall::count());
    }

    public function test_a_pharmacist_is_not_offered_the_button(): void
    {
        // They are the one being called.
        $this->assertFalse($this->bar($this->user(['pharmacist']))->instance()->canCall());
    }

    public function test_pressing_twice_does_not_stack_calls(): void
    {
        // The pharmacist should see one customer waiting, not five because
        // somebody pressed the button repeatedly.
        $caller = $this->user(['sales']);

        $bar = $this->bar($caller);
        $bar->call('callPharmacist');
        $bar->call('callPharmacist');
        $bar->call('callPharmacist');

        $this->assertSame(1, PharmacistCall::count());
    }

    public function test_a_new_call_can_be_made_once_the_last_was_answered(): void
    {
        $caller     = $this->user(['sales']);
        $pharmacist = $this->user(['pharmacist']);

        $this->bar($caller)->call('callPharmacist');
        $this->bar($pharmacist)->call('acknowledge', PharmacistCall::first()->id);

        $this->bar($caller)->call('callPharmacist');

        $this->assertSame(2, PharmacistCall::count());
    }

    // ── answering ───────────────────────────────────────────────────────

    public function test_the_pharmacist_sees_a_waiting_customer(): void
    {
        $caller = User::factory()->create(['role' => ['sales'], 'status' => 'active', 'name' => 'BOLA']);
        $this->bar($caller)->call('callPharmacist');

        $this->bar($this->user(['pharmacist']))
            ->assertSee('A customer is waiting')
            ->assertSee('BOLA');
    }

    public function test_acknowledging_clears_the_banner(): void
    {
        $this->bar($this->user(['sales']))->call('callPharmacist');

        $pharmacist = $this->user(['pharmacist']);
        $this->bar($pharmacist)->call('acknowledge', PharmacistCall::first()->id);

        $this->bar($pharmacist)->assertDontSee('A customer is waiting');
    }

    public function test_it_records_who_answered(): void
    {
        $this->bar($this->user(['sales']))->call('callPharmacist');

        $pharmacist = $this->user(['pharmacist']);
        $this->bar($pharmacist)->call('acknowledge', PharmacistCall::first()->id);

        $this->assertSame($pharmacist->id, PharmacistCall::first()->acknowledged_by);
    }

    public function test_the_caller_is_told_somebody_is_coming(): void
    {
        // Otherwise they press again wondering whether it worked.
        $caller     = $this->user(['sales']);
        $pharmacist = User::factory()->create(['role' => ['pharmacist'], 'status' => 'active', 'name' => 'ADUNNI']);

        $this->bar($caller)->call('callPharmacist');
        $this->bar($pharmacist)->call('acknowledge', PharmacistCall::first()->id);

        $this->bar($caller)->assertSee('ADUNNI')->assertSee('on the way');
    }

    public function test_a_second_pharmacist_cannot_answer_the_same_call_twice(): void
    {
        $this->bar($this->user(['sales']))->call('callPharmacist');

        $first  = $this->user(['pharmacist']);
        $second = $this->user(['pharmacist']);

        $this->bar($first)->call('acknowledge', PharmacistCall::first()->id);
        $this->bar($second)->call('acknowledge', PharmacistCall::first()->id);

        $this->assertSame($first->id, PharmacistCall::first()->acknowledged_by);
    }

    public function test_a_cashier_cannot_answer_a_call(): void
    {
        $this->bar($this->user(['sales']))->call('callPharmacist');

        $this->bar($this->user(['cashier']))->call('acknowledge', PharmacistCall::first()->id);

        $this->assertNull(PharmacistCall::first()->acknowledged_at);
    }

    // ---- being noticed when not looking at the screen ----

    public function test_the_pharmacist_is_offered_the_alert(): void
    {
        // Browsers refuse to make noise or show a notification for a page
        // nobody has interacted with, so it has to be a button.
        $this->bar($this->user(['pharmacist']))->assertSee('Turn on the alert');
    }

    public function test_the_counter_is_not_offered_it(): void
    {
        // They are the one calling; nothing rings for them.
        $this->bar($this->user(['sales']))->assertDontSee('Turn on the alert');
    }

    public function test_the_chime_needs_no_audio_file(): void
    {
        // Built in the browser: nothing to host, nothing to load, and it still
        // works on a slow connection.
        $view = file_get_contents(resource_path('views/livewire/call-pharmacist.blade.php'));

        $this->assertStringContainsString('AudioContext', $view);
        $this->assertStringNotContainsString('.mp3', $view);
        $this->assertStringNotContainsString('.wav', $view);
    }

    public function test_a_call_already_on_screen_does_not_sound_again(): void
    {
        // The component polls every five seconds. Announcing on every render
        // would chime continuously until somebody answered.
        $view = file_get_contents(resource_path('views/livewire/call-pharmacist.blade.php'));

        $this->assertStringContainsString('lastAnnounced', $view);
        $this->assertStringContainsString('id === this.lastAnnounced', $view);
    }

    // ---- ringing their phones when nobody is at a screen ----

    private function pharmacistWithPhone(?int $branchId = null): User
    {
        return User::factory()->create([
            'role'      => ['pharmacist'],
            'status'    => 'active',
            'branch_id' => $branchId,
            'phone'     => '0803' . random_int(1000000, 9999999),
        ]);
    }

    /** A call made long enough ago that the phones should ring. */
    private function staleCall(User $caller): PharmacistCall
    {
        $this->bar($caller)->call('callPharmacist');

        $call = PharmacistCall::first();
        $call->forceFill(['created_at' => now()->subMinutes(2)])->save();

        return $call->fresh();
    }

    public function test_phones_are_not_rung_while_the_setting_is_off(): void
    {
        // Off by default: it costs a message per call through the same gateway
        // that sends receipts.
        $this->pharmacistWithPhone();
        $call = $this->staleCall($this->user(['sales']));

        $this->assertFalse($call->shouldNotify());
    }

    public function test_phones_are_rung_once_it_is_switched_on(): void
    {
        AppSetting::set('pharmacist_call_alert_enabled', '1');

        $this->pharmacistWithPhone();
        $call = $this->staleCall($this->user(['sales']));

        $this->assertTrue($call->shouldNotify());
    }

    public function test_a_call_answered_in_time_never_rings_a_phone(): void
    {
        // The whole point of waiting: somebody at a screen handles it first.
        AppSetting::set('pharmacist_call_alert_enabled', '1');

        $pharmacist = $this->pharmacistWithPhone();
        $call       = $this->staleCall($this->user(['sales']));

        $this->bar($pharmacist)->call('acknowledge', $call->id);

        $this->assertFalse($call->fresh()->shouldNotify());
    }

    public function test_a_fresh_call_waits_before_ringing(): void
    {
        AppSetting::set('pharmacist_call_alert_enabled', '1');
        AppSetting::set('pharmacist_call_alert_after_seconds', 60);

        $this->pharmacistWithPhone();
        $this->bar($this->user(['sales']))->call('callPharmacist');

        $this->assertFalse(PharmacistCall::first()->shouldNotify());
    }

    public function test_the_delay_is_configurable(): void
    {
        AppSetting::set('pharmacist_call_alert_enabled', '1');
        AppSetting::set('pharmacist_call_alert_after_seconds', 15);

        $this->pharmacistWithPhone();
        $this->bar($this->user(['sales']))->call('callPharmacist');

        PharmacistCall::first()->forceFill(['created_at' => now()->subSeconds(20)])->save();

        $this->assertTrue(PharmacistCall::first()->shouldNotify());
    }

    public function test_the_phones_ring_only_once_per_call(): void
    {
        // The check rides on a poll that fires every five seconds; without the
        // mark it would message twelve times a minute until somebody came.
        AppSetting::set('pharmacist_call_alert_enabled', '1');

        $this->pharmacistWithPhone();
        $caller = $this->user(['sales']);
        $call   = $this->staleCall($caller);

        $this->bar($caller)->call('$refresh');

        $this->assertNotNull($call->fresh()->notified_at);
        $this->assertFalse($call->fresh()->shouldNotify());
    }

    public function test_only_pharmacists_with_a_number_are_rung(): void
    {
        AppSetting::set('pharmacist_call_alert_enabled', '1');

        $withPhone = $this->pharmacistWithPhone();
        User::factory()->create(['role' => ['pharmacist'], 'status' => 'active', 'phone' => null]);
        User::factory()->create(['role' => ['cashier'], 'status' => 'active', 'phone' => '08030000000']);

        $call = $this->staleCall($this->user(['sales']));

        $this->assertSame([$withPhone->id], $call->notifiable()->pluck('id')->all());
    }

    public function test_a_pharmacist_at_another_branch_is_not_rung(): void
    {
        AppSetting::set('pharmacist_call_alert_enabled', '1');

        $uyo   = Branch::create(['name' => 'UYO CITY BRANCH', 'is_main' => true]);
        $ikeja = Branch::create(['name' => 'IKEJA BRANCH', 'is_main' => false]);

        $here      = $this->pharmacistWithPhone($uyo->id);
        $elsewhere = $this->pharmacistWithPhone($ikeja->id);

        $call = $this->staleCall($this->user(['sales'], $uyo->id));

        $ids = $call->notifiable()->pluck('id')->all();

        $this->assertContains($here->id, $ids);
        $this->assertNotContains($elsewhere->id, $ids);
    }

    public function test_the_toggle_can_be_changed_in_settings(): void
    {
        Livewire::actingAs($this->user(['admin']))
            ->test(\App\Livewire\Settings\Index::class)
            ->set('pharmacist_call_alert_enabled', true)
            ->set('pharmacist_call_alert_after_seconds', 45)
            ->call('savePharmacistAlerts')
            ->assertHasNoErrors();

        $this->assertTrue(AppSetting::bool('pharmacist_call_alert_enabled'));
        $this->assertSame('45', (string) AppSetting::get('pharmacist_call_alert_after_seconds'));
    }

    public function test_an_absurd_delay_is_rejected(): void
    {
        Livewire::actingAs($this->user(['admin']))
            ->test(\App\Livewire\Settings\Index::class)
            ->set('pharmacist_call_alert_after_seconds', 99999)
            ->call('savePharmacistAlerts')
            ->assertHasErrors('pharmacist_call_alert_after_seconds');
    }

    // ── it stops ringing ────────────────────────────────────────────────

    public function test_an_unanswered_call_goes_quiet_eventually(): void
    {
        // A banner that never clears trains people to ignore it, which costs
        // more than losing the occasional call.
        $this->bar($this->user(['sales']))->call('callPharmacist');

        PharmacistCall::first()->forceFill([
            'created_at' => now()->subMinutes(PharmacistCall::EXPIRES_AFTER_MINUTES + 1),
        ])->save();

        $this->bar($this->user(['pharmacist']))->assertDontSee('A customer is waiting');
    }

    // ── it stays at its own counter ─────────────────────────────────────

    public function test_a_call_does_not_reach_another_branch(): void
    {
        $uyo   = Branch::create(['name' => 'UYO CITY BRANCH', 'is_main' => true]);
        $ikeja = Branch::create(['name' => 'IKEJA BRANCH', 'is_main' => false]);

        $this->bar($this->user(['sales'], $uyo->id))->call('callPharmacist');

        $this->bar($this->user(['pharmacist'], $ikeja->id))
            ->assertDontSee('A customer is waiting');
    }

    public function test_a_pharmacist_at_the_same_counter_does_see_it(): void
    {
        $uyo = Branch::create(['name' => 'UYO CITY BRANCH', 'is_main' => true]);

        $this->bar($this->user(['sales'], $uyo->id))->call('callPharmacist');

        $this->bar($this->user(['pharmacist'], $uyo->id))
            ->assertSee('A customer is waiting');
    }

    // ── it is everywhere ────────────────────────────────────────────────

    public function test_the_banner_reaches_the_pharmacist_on_any_page(): void
    {
        // It lives in the layout, so they do not have to be on a dashboard.
        $this->bar($this->user(['sales']))->call('callPharmacist');

        $this->actingAs($this->user(['pharmacist']))
            ->get(route('customers.index'))
            ->assertSee('A customer is waiting');
    }

    public function test_the_button_reaches_the_counter_on_any_page(): void
    {
        $this->actingAs($this->user(['sales']))
            ->get(route('pos.index'))
            ->assertSee('Call pharmacist');
    }
}
