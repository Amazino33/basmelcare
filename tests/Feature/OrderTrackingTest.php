<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Following an order, and who is allowed to.
 *
 * The order pages used to be addressed by id, with no login and no ownership
 * check on either of them. Anyone could count upwards through the numbers and
 * read a stranger's order - what they bought, what they paid, the phone number
 * and the street it was going to. These tests are mostly about that being shut,
 * and staying shut.
 *
 * A guest has no account, so the answer could not be a login. It is a token in
 * the URL: unguessable, and the only thing that opens the page.
 */
class OrderTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number'     => 'ORD-' . random_int(1000, 9999),
            'guest_name'       => 'ADAEZE OKON',
            'guest_phone'      => '08031234567',
            'subtotal'         => 4000,
            'delivery_fee'     => 2000,
            'delivery_area'    => 'Ikeja',
            'delivery_address' => '12 Awolowo Road',
            'total_amount'     => 6000,
            'fulfillment_type' => 'delivery',
            'payment_method'   => 'pay_on_delivery',
            'payment_status'   => 'pending',
            'status'           => 'pending',
        ], $overrides));
    }

    // -- the hole that was open -----------------------------------------

    public function test_an_order_cannot_be_read_by_counting_through_the_ids(): void
    {
        $order = $this->order();

        $this->get('/order/' . $order->id)->assertNotFound();
        $this->get('/order/' . $order->id . '/pay')->assertNotFound();
        $this->get('/order/' . $order->id . '/confirmation')->assertNotFound();
    }

    public function test_someone_elses_order_is_not_reachable(): void
    {
        $mine = $this->order();
        $theirs = $this->order(['guest_name' => 'CHIDI EZE', 'delivery_address' => '5 Herbert Macaulay']);

        // Holding one link tells you nothing about the next order along.
        $this->get(route('order.status', $mine->public_token))
            ->assertOk()
            ->assertDontSee('5 Herbert Macaulay')
            ->assertDontSee($theirs->order_number);
    }

    public function test_a_made_up_token_finds_nothing(): void
    {
        $this->order();

        $this->get('/order/' . str_repeat('a', 32))->assertNotFound();
    }

    public function test_the_placed_flag_opens_nothing_on_its_own(): void
    {
        // It only changes the wording at the top of the page.
        $order = $this->order();

        $this->get('/order/' . str_repeat('b', 32) . '?placed=1')->assertNotFound();
        $this->get(route('order.status', $order->public_token) . '?placed=1')->assertOk();
    }

    // -- the link works for the person who has it ------------------------

    public function test_a_guest_can_follow_their_order_without_an_account(): void
    {
        $order = $this->order();

        $this->get(route('order.status', $order->public_token))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Ikeja');
    }

    public function test_every_order_is_given_a_token_of_its_own(): void
    {
        $first  = $this->order();
        $second = $this->order();

        $this->assertNotEmpty($first->public_token);
        $this->assertSame(32, strlen($first->public_token));
        $this->assertNotSame($first->public_token, $second->public_token);
    }

    public function test_a_customers_own_order_is_reachable_the_same_way(): void
    {
        $customer = Customer::create([
            'name' => 'ADAEZE OKON', 'type' => 'retail',
            'phone' => '080' . random_int(10000000, 99999999),
        ]);

        $order = $this->order(['customer_id' => $customer->id, 'guest_name' => null]);

        $this->actingAs($customer, 'customer')
            ->get(route('order.status', $order->public_token))
            ->assertOk();
    }

    // -- what the page says ----------------------------------------------

    public function test_the_rider_is_only_shown_once_they_have_set_off(): void
    {
        // Naming somebody before they have the order invites a phone call to
        // a rider who cannot help.
        $order = $this->order([
            'status'                => 'ready',
            'delivery_person_name'  => 'MUSA BELLO',
            'delivery_person_phone' => '08031112222',
        ]);

        $this->get(route('order.status', $order->public_token))
            ->assertOk()
            ->assertDontSee('MUSA BELLO');

        $order->update(['status' => 'dispatched', 'dispatched_at' => now()]);

        $this->get(route('order.status', $order->public_token))
            ->assertOk()
            ->assertSee('MUSA BELLO')
            ->assertSee('08031112222');
    }

    public function test_a_pickup_order_is_never_shown_a_dispatch_step(): void
    {
        $pickup = $this->order([
            'fulfillment_type' => 'pickup', 'delivery_fee' => 0,
            'delivery_area' => null, 'total_amount' => 4000,
        ]);

        $labels = array_column($pickup->progressSteps(), 'label');

        $this->assertSame(
            ['Order received', 'Being prepared', 'Ready to collect', 'Collected'],
            $labels,
        );
    }

    public function test_a_delivery_order_walks_the_longer_trail(): void
    {
        $labels = array_column($this->order()->progressSteps(), 'label');

        $this->assertSame([
            'Order received',
            'Being prepared',
            'Packed, waiting for a rider',
            'On its way to you',
            'Delivered',
        ], $labels);
    }

    public function test_the_trail_marks_where_the_order_has_got_to(): void
    {
        $steps = $this->order(['status' => 'ready'])->progressSteps();

        $this->assertTrue($steps[0]['done'], 'received');
        $this->assertTrue($steps[1]['done'], 'prepared');
        $this->assertTrue($steps[2]['current'], 'packed is where it is now');
        $this->assertFalse($steps[3]['done'], 'not out yet');
        $this->assertFalse($steps[3]['current']);
    }

    public function test_a_finished_order_has_nothing_still_in_progress(): void
    {
        $steps = $this->order(['status' => 'completed'])->progressSteps();

        $this->assertTrue(collect($steps)->every(fn ($s) => $s['done']));
        $this->assertTrue(collect($steps)->every(fn ($s) => ! $s['current']));
    }

    public function test_a_cancelled_order_says_so_and_shows_no_trail(): void
    {
        $order = $this->order(['status' => 'cancelled']);

        $this->get(route('order.status', $order->public_token))
            ->assertOk()
            ->assertSee('cancelled')
            ->assertDontSee('Packed, waiting for a rider');
    }

    public function test_an_unpaid_card_order_is_told_what_is_holding_it_up(): void
    {
        $order = $this->order(['payment_method' => 'paystack', 'payment_status' => 'pending']);

        $this->get(route('order.status', $order->public_token))
            ->assertOk()
            ->assertSee('once your payment goes through');
    }

    public function test_paying_is_reached_by_the_token_too(): void
    {
        $order = $this->order(['payment_method' => 'paystack', 'payment_status' => 'paid']);

        // A paid order is sent back to its own status page rather than to a
        // second payment.
        $this->get(route('order.pay', $order->public_token))
            ->assertRedirect(route('order.status', $order->public_token));
    }
}
