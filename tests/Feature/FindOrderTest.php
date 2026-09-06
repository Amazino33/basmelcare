<?php

namespace Tests\Feature;

use App\Livewire\Shop\FindOrder;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Getting back to an order whose link has been lost.
 *
 * Order numbers run in sequence, so they prove nothing on their own. The
 * phone number is the half that does the work - which makes this page a way
 * of asking "does this person shop here", one guess at a time, unless it is
 * throttled and unless it says the same thing however it fails.
 */
class FindOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('find-order:8031234567');
    }

    private function order(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number'     => 'ORD-202609-0001',
            'guest_name'       => 'ADAEZE OKON',
            'guest_phone'      => '08031234567',
            'subtotal'         => 4000,
            'total_amount'     => 4000,
            'fulfillment_type' => 'pickup',
            'payment_method'   => 'pay_on_delivery',
            'payment_status'   => 'pending',
            'status'           => 'pending',
        ], $overrides));
    }

    public function test_the_right_pair_gets_you_to_your_order(): void
    {
        $order = $this->order();

        Livewire::test(FindOrder::class)
            ->set('order_number', 'ORD-202609-0001')
            ->set('phone', '08031234567')
            ->call('find')
            ->assertRedirect(route('order.status', $order->public_token));
    }

    public function test_the_number_alone_is_not_enough(): void
    {
        // Order numbers run in sequence. If the number alone opened the
        // order, this page would be worse than the hole it replaced.
        $this->order();

        Livewire::test(FindOrder::class)
            ->set('order_number', 'ORD-202609-0001')
            ->set('phone', '08099999999')
            ->call('find')
            ->assertHasErrors('order_number')
            ->assertNoRedirect();
    }

    public function test_the_same_phone_written_differently_still_works(): void
    {
        // People give 0803..., 234803... and +234 803 ... on different days.
        $order = $this->order();

        foreach (['+234 803 123 4567', '2348031234567', '0803-123-4567'] as $written) {
            RateLimiter::clear('find-order:8031234567');

            Livewire::test(FindOrder::class)
                ->set('order_number', 'ORD-202609-0001')
                ->set('phone', $written)
                ->call('find')
                ->assertRedirect(route('order.status', $order->public_token));
        }
    }

    public function test_a_customers_own_number_finds_their_order(): void
    {
        $customer = Customer::create([
            'name' => 'ADAEZE OKON', 'type' => 'retail', 'phone' => '08031234567',
        ]);

        $order = $this->order([
            'customer_id' => $customer->id, 'guest_phone' => null, 'guest_name' => null,
        ]);

        Livewire::test(FindOrder::class)
            ->set('order_number', 'ORD-202609-0001')
            ->set('phone', '08031234567')
            ->call('find')
            ->assertRedirect(route('order.status', $order->public_token));
    }

    // -- it must not become a way of asking about people -----------------

    public function test_a_wrong_phone_and_a_missing_order_give_the_same_answer(): void
    {
        $this->order();

        $wrongPhone = Livewire::test(FindOrder::class)
            ->set('order_number', 'ORD-202609-0001')
            ->set('phone', '08099999999')
            ->call('find')
            ->errors()->first('order_number');

        RateLimiter::clear('find-order:8099999999');

        $noSuchOrder = Livewire::test(FindOrder::class)
            ->set('order_number', 'ORD-202609-9999')
            ->set('phone', '08099999999')
            ->call('find')
            ->errors()->first('order_number');

        $this->assertSame($wrongPhone, $noSuchOrder,
            'otherwise the page tells you which order numbers are real');
    }

    public function test_guessing_is_throttled(): void
    {
        $this->order();

        // Numbers that match nothing: a hit among them would clear the count
        // and the throttle would never be reached.
        for ($i = 0; $i < 5; $i++) {
            Livewire::test(FindOrder::class)
                ->set('order_number', 'ORD-000000-000' . $i)
                ->set('phone', '08031234567')
                ->call('find');
        }

        Livewire::test(FindOrder::class)
            ->set('order_number', 'ORD-202609-0001')
            ->set('phone', '08031234567')
            ->call('find')
            ->assertHasErrors('order_number')
            ->assertNoRedirect();
    }

    public function test_a_success_clears_the_count(): void
    {
        // Somebody mistyping their own number twice should not be locked out
        // for a quarter of an hour after they get it right.
        $order = $this->order();

        Livewire::test(FindOrder::class)
            ->set('order_number', 'ORD-202609-XXXX')
            ->set('phone', '08031234567')
            ->call('find');

        Livewire::test(FindOrder::class)
            ->set('order_number', 'ORD-202609-0001')
            ->set('phone', '08031234567')
            ->call('find')
            ->assertRedirect(route('order.status', $order->public_token));

        $this->assertSame(0, RateLimiter::attempts('find-order:8031234567'));
    }

    public function test_both_fields_are_required(): void
    {
        Livewire::test(FindOrder::class)
            ->call('find')
            ->assertHasErrors(['order_number', 'phone']);
    }

    public function test_the_page_loads(): void
    {
        $this->get(route('order.find'))->assertOk()->assertSee('Find your order');
    }
}
