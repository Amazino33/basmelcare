<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InsuranceClaim;
use App\Models\InsurancePlan;
use App\Models\InsuranceSubscription;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cover at online checkout.
 *
 * The same cover as the counter, drawn from the same pool - so the two must
 * not each hand out a full allowance. Cover is taken as the order is placed
 * rather than when it is paid for: two orders minutes apart would otherwise
 * both be promised it, and the second customer would be undercharged with
 * nothing left to draw on.
 */
class InsuranceAtCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AppSetting::set('insurance_enabled', '1');
    }

    private function customer(): Customer
    {
        return Customer::create([
            'name'  => 'ADAEZE OKON',
            'type'  => 'retail',
            'email' => 'adaeze' . random_int(100, 999) . '@example.com',
            'phone' => '080' . random_int(10000000, 99999999),
        ]);
    }

    private function covered(Customer $customer, array $planOverrides = []): InsuranceSubscription
    {
        $plan = InsurancePlan::create(array_merge([
            'name' => 'Bronze', 'code' => 'BRONZE-' . random_int(100, 999),
            'monthly_premium' => 5000, 'monthly_cover' => 10000,
            'copay_percent' => 0, 'waiting_days' => 0, 'grace_days' => 7,
            'is_active' => true,
        ], $planOverrides));

        $subscription = InsuranceSubscription::create([
            'customer_id'       => $customer->id,
            'insurance_plan_id' => $plan->id,
        ]);

        $subscription->recordPremium(5000, 'cash');

        return $subscription->fresh(['plan']);
    }

    private function stockedProduct(float $price = 4000, float $cost = 2500): Product
    {
        $product = Product::create([
            'name'          => 'PARACETAMOL ' . random_int(100, 999),
            'category_id'   => Category::firstOrCreate(['name' => 'MEDICINE'])->id,
            'selling_price' => $price,
            'reorder_level' => 1,
        ]);

        Batch::create([
            'product_id' => $product->id, 'batch_number' => 'B' . random_int(100, 999),
            'expiry_date' => now()->addYear(), 'cost_price' => $cost, 'quantity' => 50,
        ]);

        return $product;
    }

    /** Put one product in the session cart, as the shop does. */
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

    private function checkout(?Customer $as = null)
    {
        $component = $as
            ? Livewire::actingAs($as, 'customer')->test(\App\Livewire\Shop\Checkout::class)
            : Livewire::test(\App\Livewire\Shop\Checkout::class);

        return $component
            ->set('checkout_mode', 'account')
            ->set('fulfillment_type', 'pickup')
            ->set('payment_method', 'pay_on_delivery');
    }

    // ── what the customer is shown ──────────────────────────────────────

    public function test_a_covered_customer_sees_what_their_cover_pays(): void
    {
        $customer = $this->customer();
        $this->covered($customer);
        $this->fillCart($this->stockedProduct(4000));

        $quote = $this->checkout($customer)->instance()->coverQuote();

        $this->assertSame(4000.0, $quote['covered']);
    }

    public function test_a_guest_is_shown_nothing_about_cover(): void
    {
        // Cover belongs to a named person; a guest checkout has nobody to
        // charge it to.
        $this->fillCart($this->stockedProduct());

        $this->assertNull($this->checkout()->instance()->coverQuote());
    }

    public function test_a_customer_without_a_plan_is_shown_nothing(): void
    {
        $this->fillCart($this->stockedProduct());

        $this->assertNull($this->checkout($this->customer())->instance()->coverQuote());
    }

    public function test_a_lapsed_customer_is_told_why(): void
    {
        $customer = $this->customer();
        $this->covered($customer, ['grace_days' => 0]);
        $this->travel(45)->days();
        $this->fillCart($this->stockedProduct());

        $quote = $this->checkout($customer)->instance()->coverQuote();

        $this->assertSame(0.0, $quote['covered']);
        $this->assertStringContainsString('overdue', $quote['reason']);
    }

    // ── placing the order ───────────────────────────────────────────────

    public function test_the_order_total_drops_by_what_cover_paid(): void
    {
        $customer = $this->customer();
        $this->covered($customer);
        $this->fillCart($this->stockedProduct(4000));

        $this->checkout($customer)->call('placeOrder');

        $order = Order::sole();

        $this->assertEquals(4000, $order->subtotal);
        $this->assertEquals(4000, $order->insurance_covered);
        $this->assertEquals(0, $order->total_amount);
    }

    public function test_delivery_is_never_covered(): void
    {
        // Cover is for medicine. The fee is a service the pharmacy pays for.
        $customer = $this->customer();
        $this->covered($customer);
        $this->fillCart($this->stockedProduct(4000));

        $this->checkout($customer)
            ->set('fulfillment_type', 'delivery')
            ->set('delivery_address', '12 Aka Road, Uyo')
            ->set('delivery_phone', '08031234567')
            ->call('placeOrder');

        $order = Order::sole();

        $this->assertEquals(4000, $order->insurance_covered);
        $this->assertEquals($order->delivery_fee, $order->total_amount,
            'The delivery fee was covered, or was not charged.');
    }

    public function test_the_cover_is_spent_when_the_order_is_placed(): void
    {
        $customer     = $this->customer();
        $subscription = $this->covered($customer);
        $this->fillCart($this->stockedProduct(4000));

        $this->checkout($customer)->call('placeOrder');

        $this->assertSame(6000.0, $subscription->fresh(['plan'])->coverRemaining());
    }

    public function test_the_claim_books_what_the_stock_is_worth(): void
    {
        // An online order has no batch allocated until the pharmacy picks it,
        // so booking nothing would flatter the cover report into showing free
        // medicine as costless.
        $customer = $this->customer();
        $this->covered($customer);
        $this->fillCart($this->stockedProduct(4000, cost: 2500));

        $this->checkout($customer)->call('placeOrder');

        $this->assertEquals(2500, InsuranceClaim::sole()->cost_amount);
    }

    public function test_the_claim_points_at_the_order(): void
    {
        $customer = $this->customer();
        $this->covered($customer);
        $this->fillCart($this->stockedProduct());

        $this->checkout($customer)->call('placeOrder');

        $this->assertSame(Order::sole()->id, InsuranceClaim::sole()->order_id);
    }

    public function test_the_ceiling_holds_online_too(): void
    {
        $customer = $this->customer();
        $this->covered($customer, ['monthly_cover' => 2500]);
        $this->fillCart($this->stockedProduct(4000));

        $this->checkout($customer)->call('placeOrder');

        $order = Order::sole();

        $this->assertEquals(2500, $order->insurance_covered);
        $this->assertEquals(1500, $order->total_amount, 'The customer was not asked for the excess.');
    }

    public function test_two_orders_cannot_each_take_the_whole_cover(): void
    {
        // The same pool as the counter. This is why cover is spent at placement
        // rather than at payment.
        $customer     = $this->customer();
        $subscription = $this->covered($customer, ['monthly_cover' => 4000]);
        $product      = $this->stockedProduct(4000);

        foreach ([1, 2] as $_) {
            $this->fillCart($product);
            $this->checkout($customer)->call('placeOrder');
        }

        $this->assertEquals(4000, $subscription->fresh()->cover_used);
        $this->assertSame(1, InsuranceClaim::count());
        $this->assertEquals(4000, Order::orderByDesc('id')->first()->total_amount,
            'The second order was undercharged against cover that was gone.');
    }

    public function test_cover_already_spent_at_the_counter_is_gone_online(): void
    {
        $customer     = $this->customer();
        $subscription = $this->covered($customer, ['monthly_cover' => 4000]);
        $subscription->drawDown(4000);   // as if the till had taken it

        $this->fillCart($this->stockedProduct(4000));
        $this->checkout($customer)->call('placeOrder');

        $this->assertEquals(4000, Order::sole()->total_amount);
        $this->assertEquals(0, Order::sole()->insurance_covered);
    }

    public function test_excluded_categories_are_not_covered_online_either(): void
    {
        $cosmetics = Category::create(['name' => 'COSMETICS']);
        $customer  = $this->customer();
        $this->covered($customer, ['excluded_categories' => [$cosmetics->id]]);

        $product = $this->stockedProduct(3000);
        $product->update(['category_id' => $cosmetics->id]);
        $this->fillCart($product);

        $this->checkout($customer)->call('placeOrder');

        $this->assertEquals(0, Order::sole()->insurance_covered);
        $this->assertEquals(3000, Order::sole()->total_amount);
    }

    // ── the switch ──────────────────────────────────────────────────────

    public function test_nothing_is_covered_online_while_the_scheme_is_off(): void
    {
        $customer = $this->customer();
        $this->covered($customer);
        AppSetting::set('insurance_enabled', '0');

        $this->fillCart($this->stockedProduct(4000));
        $this->checkout($customer)->call('placeOrder');

        $this->assertEquals(0, Order::sole()->insurance_covered);
        $this->assertEquals(4000, Order::sole()->total_amount);
        $this->assertSame(0, InsuranceClaim::count());
    }

    // ── giving it back ──────────────────────────────────────────────────

    public function test_cancelling_an_order_gives_the_cover_back(): void
    {
        // Cover is spent when the order is placed. A customer must not lose the
        // month's allowance to an order the pharmacy could not fill.
        $customer     = $this->customer();
        $subscription = $this->covered($customer);
        $this->fillCart($this->stockedProduct(4000));

        $this->checkout($customer)->call('placeOrder');
        $this->assertSame(6000.0, $subscription->fresh(['plan'])->coverRemaining());

        $staff = \App\Models\User::factory()->create(['role' => ['admin'], 'status' => 'active']);

        Livewire::actingAs($staff)
            ->test(\App\Livewire\OnlineOrders\Index::class)
            ->call('cancelOrder', Order::sole()->id);

        $this->assertSame(10000.0, $subscription->fresh(['plan'])->coverRemaining());
    }

    public function test_a_cancelled_order_leaves_no_claim_behind(): void
    {
        // A claim that outlived its order would show in the cover report as
        // medicine given away that never left the shelf.
        $customer = $this->customer();
        $this->covered($customer);
        $this->fillCart($this->stockedProduct(4000));

        $this->checkout($customer)->call('placeOrder');
        $this->assertSame(1, InsuranceClaim::count());

        $staff = \App\Models\User::factory()->create(['role' => ['admin'], 'status' => 'active']);

        Livewire::actingAs($staff)
            ->test(\App\Livewire\OnlineOrders\Index::class)
            ->call('cancelOrder', Order::sole()->id);

        $this->assertSame(0, InsuranceClaim::count());
        $this->assertEquals(4000, Order::sole()->total_amount,
            'The cancelled order still showed a discounted total.');
    }
}
