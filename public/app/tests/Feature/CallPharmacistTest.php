<?php

namespace Tests\Feature;

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
        $this->bar($this->user(['sales']))->call('call');

        $this->assertSame(1, PharmacistCall::count());
    }

    public function test_a_cashier_can_call(): void
    {
        $this->bar($this->user(['cashier']))->call('call');

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
        $bar->call('call');
        $bar->call('call');
        $bar->call('call');

        $this->assertSame(1, PharmacistCall::count());
    }

    public function test_a_new_call_can_be_made_once_the_last_was_answered(): void
    {
        $caller     = $this->user(['sales']);
        $pharmacist = $this->user(['pharmacist']);

        $this->bar($caller)->call('call');
        $this->bar($pharmacist)->call('acknowledge', PharmacistCall::first()->id);

        $this->bar($caller)->call('call');

        $this->assertSame(2, PharmacistCall::count());
    }

    // ── answering ───────────────────────────────────────────────────────

    public function test_the_pharmacist_sees_a_waiting_customer(): void
    {
        $caller = User::factory()->create(['role' => ['sales'], 'status' => 'active', 'name' => 'BOLA']);
        $this->bar($caller)->call('call');

        $this->bar($this->user(['pharmacist']))
            ->assertSee('A customer is waiting')
            ->assertSee('BOLA');
    }

    public function test_acknowledging_clears_the_banner(): void
    {
        $this->bar($this->user(['sales']))->call('call');

        $pharmacist = $this->user(['pharmacist']);
        $this->bar($pharmacist)->call('acknowledge', PharmacistCall::first()->id);

        $this->bar($pharmacist)->assertDontSee('A customer is waiting');
    }

    public function test_it_records_who_answered(): void
    {
        $this->bar($this->user(['sales']))->call('call');

        $pharmacist = $this->user(['pharmacist']);
        $this->bar($pharmacist)->call('acknowledge', PharmacistCall::first()->id);

        $this->assertSame($pharmacist->id, PharmacistCall::first()->acknowledged_by);
    }

    public function test_the_caller_is_told_somebody_is_coming(): void
    {
        // Otherwise they press again wondering whether it worked.
        $caller     = $this->user(['sales']);
        $pharmacist = User::factory()->create(['role' => ['pharmacist'], 'status' => 'active', 'name' => 'ADUNNI']);

        $this->bar($caller)->call('call');
        $this->bar($pharmacist)->call('acknowledge', PharmacistCall::first()->id);

        $this->bar($caller)->assertSee('ADUNNI')->assertSee('on the way');
    }

    public function test_a_second_pharmacist_cannot_answer_the_same_call_twice(): void
    {
        $this->bar($this->user(['sales']))->call('call');

        $first  = $this->user(['pharmacist']);
        $second = $this->user(['pharmacist']);

        $this->bar($first)->call('acknowledge', PharmacistCall::first()->id);
        $this->bar($second)->call('acknowledge', PharmacistCall::first()->id);

        $this->assertSame($first->id, PharmacistCall::first()->acknowledged_by);
    }

    public function test_a_cashier_cannot_answer_a_call(): void
    {
        $this->bar($this->user(['sales']))->call('call');

        $this->bar($this->user(['cashier']))->call('acknowledge', PharmacistCall::first()->id);

        $this->assertNull(PharmacistCall::first()->acknowledged_at);
    }

    // ── it stops ringing ────────────────────────────────────────────────

    public function test_an_unanswered_call_goes_quiet_eventually(): void
    {
        // A banner that never clears trains people to ignore it, which costs
        // more than losing the occasional call.
        $this->bar($this->user(['sales']))->call('call');

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

        $this->bar($this->user(['sales'], $uyo->id))->call('call');

        $this->bar($this->user(['pharmacist'], $ikeja->id))
            ->assertDontSee('A customer is waiting');
    }

    public function test_a_pharmacist_at_the_same_counter_does_see_it(): void
    {
        $uyo = Branch::create(['name' => 'UYO CITY BRANCH', 'is_main' => true]);

        $this->bar($this->user(['sales'], $uyo->id))->call('call');

        $this->bar($this->user(['pharmacist'], $uyo->id))
            ->assertSee('A customer is waiting');
    }

    // ── it is everywhere ────────────────────────────────────────────────

    public function test_the_banner_reaches_the_pharmacist_on_any_page(): void
    {
        // It lives in the layout, so they do not have to be on a dashboard.
        $this->bar($this->user(['sales']))->call('call');

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
