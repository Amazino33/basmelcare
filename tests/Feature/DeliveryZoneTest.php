<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Batch;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * What delivery costs, and who decides it.
 *
 * Delivery used to be a flat 1500 written into the checkout - the same charge
 * to a street behind the pharmacy and to the other end of the state, and a
 * price the pharmacy could not change without a developer.
 *
 * The tests that matter here are the ones about money moving in the wrong
 * direction: a fee that follows the zone rather than the customer's word for
 * it, an order that keeps its own copy of what was agreed, and a free-delivery
 * threshold that cannot be met by the delivery fee itself.
 */
class DeliveryZoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The migration carries the old flat fee in as one zone so that
        // deploying this changes nothing for a live shop. Every test below is
        // about a pharmacy that has set its own areas up, so it starts from an
        // empty list and says what it wants.
        DeliveryZone::query()->delete();
    }

    private function zone(string $name, float $fee, array $overrides = []): DeliveryZone
    {
        return DeliveryZone::create(array_merge([
            'name' => $name, 'fee' => $fee, 'is_active' => true, 'sort_order' => 0,
        ], $overrides));
    }

    private function stockedProduct(float $price = 4000): Product
    {
        $product = Product::create([
            'name'          => 'PARACETAMOL ' . random_int(100, 999),
            'category_id'   => Category::firstOrCreate(['name' => 'MEDICINE'])->id,
            'selling_price' => $price,
            'reorder_level' => 1,
        ]);

        Batch::create([
            'product_id' => $product->id, 'batch_number' => 'B' . random_int(100, 999),
            'expiry_date' => now()->addYear(), 'cost_price' => $price / 2, 'quantity' => 50,
        ]);

        return $product;
    }

    private function fillCart(Product $product, int $qty = 1): void
    {
        session()->put('cart', [
            $product->id => [
                'product_id' => $product->id,
                'name'       => $product->name,
                'price'      => (float) $product->selling_price,
                'quantity'   => $qty,
            ],
        ]);
    }

    /** A guest checkout filled in far enough to be placed. */
    private function checkout()
    {
        return Livewire::test(\App\Livewire\Shop\Checkout::class)
            ->set('checkout_mode', 'guest')
            ->set('guest_name', 'ADAEZE OKON')
            ->set('guest_phone', '08031234567')
            ->set('payment_method', 'pay_on_delivery');
    }

    // -- the fee follows the area ---------------------------------------

    public function test_the_fee_charged_is_the_one_for_the_area_chosen(): void
    {
        $near = $this->zone('Behind the pharmacy', 500);
        $far  = $this->zone('Other side of town', 3000);

        $this->fillCart($this->stockedProduct(4000));

        $this->checkout()
            ->set('fulfillment_type', 'delivery')
            ->set('delivery_zone_id', $far->id)
            ->set('delivery_address', '12 Awolowo Road')
            ->set('delivery_phone', '08031234567')
            ->call('placeOrder');

        $order = Order::latest('id')->first();

        $this->assertSame(3000.0, (float) $order->delivery_fee);
        $this->assertSame($far->id, $order->delivery_zone_id);
        $this->assertSame(7000.0, (float) $order->total_amount);
        $this->assertNotSame($near->id, $order->delivery_zone_id);
    }

    public function test_pickup_pays_nothing_even_with_an_area_selected(): void
    {
        // The zone stays set as the customer flips between the two, and
        // charging for a delivery that is not happening would be theft by
        // leftover state.
        $zone = $this->zone('Ikeja', 2000);
        $this->fillCart($this->stockedProduct(4000));

        $this->checkout()
            ->set('delivery_zone_id', $zone->id)
            ->set('fulfillment_type', 'pickup')
            ->call('placeOrder');

        $order = Order::latest('id')->first();

        $this->assertSame(0.0, (float) $order->delivery_fee);
        $this->assertNull($order->delivery_zone_id);
        $this->assertNull($order->delivery_area);
        $this->assertSame(4000.0, (float) $order->total_amount);
    }

    public function test_an_area_the_shop_no_longer_serves_cannot_be_ordered_to(): void
    {
        // Somebody holding the page open from before it was turned off.
        $retired = $this->zone('Old route', 100, ['is_active' => false]);
        $this->zone('Ikeja', 2000);
        $this->fillCart($this->stockedProduct(4000));

        $this->checkout()
            ->set('fulfillment_type', 'delivery')
            ->set('delivery_zone_id', $retired->id)
            ->set('delivery_address', '12 Awolowo Road')
            ->set('delivery_phone', '08031234567')
            ->call('placeOrder')
            ->assertHasErrors('delivery_zone_id');

        $this->assertSame(0, Order::count());
    }

    public function test_delivery_cannot_be_ordered_without_choosing_an_area(): void
    {
        $this->zone('Ikeja', 2000);
        $this->zone('Lekki', 3500);
        $this->fillCart($this->stockedProduct(4000));

        $this->checkout()
            ->set('fulfillment_type', 'delivery')
            ->set('delivery_address', '12 Awolowo Road')
            ->set('delivery_phone', '08031234567')
            ->call('placeOrder')
            ->assertHasErrors('delivery_zone_id');
    }

    public function test_one_area_is_chosen_for_the_customer(): void
    {
        // A shop with a single rate should not make anybody pick from a list
        // of one.
        $only = $this->zone('Standard delivery', 1500);

        $this->assertSame($only->id, Livewire::test(\App\Livewire\Shop\Checkout::class)
            ->get('delivery_zone_id'));
    }

    // -- the order is the record ----------------------------------------

    public function test_repricing_an_area_does_not_change_what_an_old_order_was_charged(): void
    {
        $zone = $this->zone('Ikeja', 2000);
        $this->fillCart($this->stockedProduct(4000));

        $this->checkout()
            ->set('fulfillment_type', 'delivery')
            ->set('delivery_zone_id', $zone->id)
            ->set('delivery_address', '12 Awolowo Road')
            ->set('delivery_phone', '08031234567')
            ->call('placeOrder');

        $order = Order::latest('id')->first();

        $zone->update(['fee' => 9000, 'name' => 'Ikeja & Ogba']);

        $order->refresh();

        $this->assertSame(2000.0, (float) $order->delivery_fee, 'the fee agreed at the time');
        $this->assertSame('Ikeja', $order->delivery_area, 'the area as it was named then');
        $this->assertSame(6000.0, (float) $order->total_amount);
    }

    // -- free delivery --------------------------------------------------

    public function test_a_big_enough_basket_delivers_free(): void
    {
        AppSetting::set('delivery_free_over', 20000);
        $zone = $this->zone('Ikeja', 2000);

        $this->fillCart($this->stockedProduct(25000));

        $this->checkout()
            ->set('fulfillment_type', 'delivery')
            ->set('delivery_zone_id', $zone->id)
            ->set('delivery_address', '12 Awolowo Road')
            ->set('delivery_phone', '08031234567')
            ->call('placeOrder');

        $order = Order::latest('id')->first();

        $this->assertSame(0.0, (float) $order->delivery_fee);
        $this->assertSame(25000.0, (float) $order->total_amount);
    }

    public function test_the_delivery_fee_cannot_buy_its_own_free_delivery(): void
    {
        // Goods of 19,000 plus a 2,000 fee clears a 20,000 threshold on the
        // total. Reading the threshold against the total would waive the fee,
        // which would drop the total back under it - the sort of loop that
        // ends with the shop delivering free to everybody who is close.
        AppSetting::set('delivery_free_over', 20000);
        $zone = $this->zone('Ikeja', 2000);

        $this->fillCart($this->stockedProduct(19000));

        $fee = Livewire::test(\App\Livewire\Shop\Checkout::class)
            ->set('fulfillment_type', 'delivery')
            ->set('delivery_zone_id', $zone->id)
            ->instance()
            ->deliveryFee();

        $this->assertSame(2000.0, $fee);
    }

    public function test_a_threshold_of_zero_never_waives_the_fee(): void
    {
        AppSetting::set('delivery_free_over', 0);
        $zone = $this->zone('Ikeja', 2000);

        $this->fillCart($this->stockedProduct(500000));

        $fee = Livewire::test(\App\Livewire\Shop\Checkout::class)
            ->set('fulfillment_type', 'delivery')
            ->set('delivery_zone_id', $zone->id)
            ->instance()
            ->deliveryFee();

        $this->assertSame(2000.0, $fee);
    }

    // -- when the shop is not delivering --------------------------------

    public function test_with_no_areas_set_up_the_shop_is_collection_only(): void
    {
        $this->fillCart($this->stockedProduct(4000));

        $this->assertFalse(DeliveryZone::deliveryOffered());

        Livewire::test(\App\Livewire\Shop\Checkout::class)
            ->assertSet('fulfillment_type', 'pickup')
            ->assertViewHas('deliveryOffered', false);
    }

    public function test_delivery_can_be_switched_off_without_removing_the_areas(): void
    {
        // A rider off sick for a week should not cost the pharmacy its
        // carefully priced list of areas.
        $this->zone('Ikeja', 2000);
        AppSetting::set('delivery_enabled', '0');

        $this->assertFalse(DeliveryZone::deliveryOffered());

        Livewire::test(\App\Livewire\Shop\Checkout::class)
            ->assertSet('fulfillment_type', 'pickup');

        AppSetting::set('delivery_enabled', '1');

        $this->assertTrue(DeliveryZone::deliveryOffered());
    }

    // -- what the customer is told afterwards ---------------------------

    /** An order at some stage of being fulfilled. */
    private function placedOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number'     => 'ORD-' . random_int(1000, 9999),
            'subtotal'         => 4000,
            'delivery_fee'     => 2000,
            'delivery_area'    => 'Ikeja',
            'total_amount'     => 6000,
            'fulfillment_type' => 'delivery',
            'payment_method'   => 'pay_on_delivery',
            'payment_status'   => 'pending',
            'status'           => 'pending',
        ], $overrides));
    }

    public function test_ready_means_something_different_to_each_kind_of_customer(): void
    {
        // "Ready" is the pharmacy's word for the shelf work being done. To
        // somebody waiting at home it reads as though it has arrived.
        $this->assertSame(
            'Packed, waiting for a rider',
            $this->placedOrder(['status' => 'ready'])->progressLabel(),
        );

        $this->assertSame(
            'Ready to collect',
            $this->placedOrder(['status' => 'ready', 'fulfillment_type' => 'pickup'])->progressLabel(),
        );
    }

    public function test_a_dispatched_order_says_it_is_on_its_way(): void
    {
        $order = $this->placedOrder([
            'status'                => 'dispatched',
            'dispatched_at'         => now(),
            'delivery_person_name'  => 'MUSA BELLO',
            'delivery_person_phone' => '08031112222',
        ]);

        $this->assertSame('On its way to you', $order->progressLabel());
        $this->assertTrue($order->isOnItsWay());
    }

    public function test_a_finished_order_is_worded_for_how_it_was_taken(): void
    {
        $this->assertSame('Delivered', $this->placedOrder(['status' => 'completed'])->progressLabel());

        $this->assertSame('Collected', $this->placedOrder([
            'status' => 'completed', 'fulfillment_type' => 'pickup',
        ])->progressLabel());
    }

    public function test_the_rider_is_only_named_once_they_have_set_off(): void
    {
        // Naming somebody before they have the order invites a phone call to
        // a rider who cannot help.
        $waiting = $this->placedOrder([
            'status'               => 'ready',
            'delivery_person_name' => 'MUSA BELLO',
        ]);

        $this->assertFalse($waiting->isOnItsWay());
    }

    public function test_areas_are_offered_in_the_order_the_pharmacy_set(): void
    {
        $this->zone('Far but common', 3000, ['sort_order' => 0]);
        $this->zone('Cheap and rare', 300, ['sort_order' => 1]);

        $names = Livewire::test(\App\Livewire\Shop\Checkout::class)
            ->instance()->zones()->pluck('name')->all();

        $this->assertSame(['Far but common', 'Cheap and rare'], $names);
    }
}
