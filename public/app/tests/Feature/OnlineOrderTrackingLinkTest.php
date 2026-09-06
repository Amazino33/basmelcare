<?php

namespace Tests\Feature;

use App\Livewire\OnlineOrders\Index;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The link staff read out when a customer rings.
 *
 * The tracking link is unguessable, which means staff cannot look an order up
 * for somebody who has lost it - reading it back to them is the only way. It
 * therefore has to be on the order detail, and it has to be the real one.
 */
class OnlineOrderTrackingLinkTest extends TestCase
{
    use RefreshDatabase;

    private function order(): Order
    {
        return Order::create([
            'order_number'     => 'ORD-202609-0001',
            'guest_name'       => 'ADAEZE OKON',
            'guest_phone'      => '08031234567',
            'subtotal'         => 4000,
            'delivery_fee'     => 2000,
            'delivery_area'    => 'Ikeja',
            'total_amount'     => 6000,
            'fulfillment_type' => 'delivery',
            'payment_method'   => 'pay_on_delivery',
            'payment_status'   => 'pending',
            'status'           => 'pending',
        ]);
    }

    private function detail(Order $order)
    {
        $user = User::factory()->create(['role' => ['sales'], 'status' => 'active']);

        return Livewire::actingAs($user)->test(Index::class)->call('viewDetails', $order->id);
    }

    public function test_the_order_detail_carries_the_customers_own_link(): void
    {
        config(['app.public_site_url' => 'https://basmelcare.test']);

        $order = $this->order();

        $this->detail($order)
            ->assertSee('https://basmelcare.test/order/' . $order->public_token)
            ->assertSee('/find-order');
    }

    public function test_the_link_is_left_out_rather_than_shown_broken(): void
    {
        // Without the public site's address there is no link to give, and
        // half a URL read down the phone is worse than none.
        config(['app.public_site_url' => '']);

        $this->detail($this->order())->assertDontSee('/order/');
    }

    public function test_the_area_is_shown_beside_the_delivery_charge(): void
    {
        config(['app.public_site_url' => 'https://basmelcare.test']);

        $this->detail($this->order())->assertSee('Ikeja');
    }
}
