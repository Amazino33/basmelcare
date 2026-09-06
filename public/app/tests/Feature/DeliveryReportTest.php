<?php

namespace Tests\Feature;

use App\Livewire\Delivery\Report;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * What the pharmacy earned for delivering, and what it has not earned yet.
 *
 * The figures here are the sort that get added together by accident and then
 * relied on. A fee on an order still out with a rider is not income; a fee on
 * a cancelled order is not anything. Each of those is its own test, because
 * each of them would look plausible if it were wrong.
 */
class DeliveryReportTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $roles = ['admin']): User
    {
        return User::factory()->create(['role' => $roles, 'status' => 'active']);
    }

    private function delivery(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number'     => 'ORD-' . random_int(10000, 99999),
            'subtotal'         => 10000,
            'delivery_fee'     => 2000,
            'delivery_area'    => 'Ikeja',
            'total_amount'     => 12000,
            'fulfillment_type' => 'delivery',
            'payment_method'   => 'pay_on_delivery',
            'payment_status'   => 'paid',
            'status'           => 'completed',
        ], $overrides));
    }

    private function report(array $roles = ['admin'])
    {
        return Livewire::actingAs($this->user($roles))->test(Report::class);
    }

    // -- what counts as earned -------------------------------------------

    public function test_only_deliveries_that_arrived_count_as_earned(): void
    {
        $this->delivery(['delivery_fee' => 2000, 'status' => 'completed']);
        $this->delivery(['delivery_fee' => 3000, 'status' => 'dispatched']);
        $this->delivery(['delivery_fee' => 5000, 'status' => 'pending']);

        $this->report()
            ->assertViewHas('earned', 2000.0)
            ->assertViewHas('stillOutFees', 3000.0)
            ->assertViewHas('deliveredCount', 1)
            ->assertViewHas('stillOutCount', 1)
            ->assertViewHas('inHandCount', 1);
    }

    public function test_a_cancelled_delivery_earns_nothing(): void
    {
        // Counting its fee would report money nobody ever paid.
        $this->delivery(['delivery_fee' => 2000, 'status' => 'completed']);
        $this->delivery(['delivery_fee' => 9000, 'status' => 'cancelled']);

        $this->report()
            ->assertViewHas('earned', 2000.0)
            ->assertViewHas('cancelledCount', 1);
    }

    public function test_earned_is_not_the_same_as_collected(): void
    {
        // A pay-on-delivery order that arrived has earned its fee. The cash
        // exists once the rider hands it in, which is a separate event.
        $this->delivery(['delivery_fee' => 2000, 'payment_status' => 'paid']);
        $this->delivery(['delivery_fee' => 1500, 'payment_status' => 'pending']);

        $this->report()
            ->assertViewHas('earned', 3500.0)
            ->assertViewHas('earnedPaid', 2000.0);
    }

    public function test_collection_orders_are_not_in_the_delivery_report(): void
    {
        $this->delivery(['delivery_fee' => 2000]);

        Order::create([
            'order_number' => 'ORD-PICKUP', 'subtotal' => 8000, 'delivery_fee' => 0,
            'total_amount' => 8000, 'fulfillment_type' => 'pickup',
            'payment_method' => 'cash', 'payment_status' => 'paid', 'status' => 'completed',
        ]);

        $this->report()->assertViewHas('totalOrders', 1);
    }

    // -- how it is grouped -----------------------------------------------

    public function test_areas_are_grouped_on_what_the_order_recorded(): void
    {
        // Not on the zone it points at: renaming a zone must not move last
        // month's deliveries under a heading that did not exist then.
        $this->delivery(['delivery_area' => 'Ikeja', 'delivery_fee' => 2000]);
        $this->delivery(['delivery_area' => 'Ikeja', 'delivery_fee' => 2000]);
        $this->delivery(['delivery_area' => 'Lekki', 'delivery_fee' => 3500]);

        $byArea = $this->report()->viewData('byArea')->keyBy('area');

        $this->assertSame(2, $byArea['Ikeja']['orders']);
        $this->assertSame(4000.0, $byArea['Ikeja']['fees']);
        $this->assertSame(3500.0, $byArea['Lekki']['fees']);
    }

    public function test_an_order_with_no_area_recorded_is_shown_rather_than_dropped(): void
    {
        // Orders placed before areas existed. Silently leaving them out would
        // make the report disagree with the till.
        $this->delivery(['delivery_area' => null, 'delivery_fee' => 1500]);

        $areas = $this->report()->viewData('byArea')->pluck('area')->all();

        $this->assertContains('Not recorded', $areas);
    }

    public function test_riders_are_counted_by_the_name_typed_at_dispatch(): void
    {
        $this->delivery([
            'delivery_person_name' => 'MUSA BELLO', 'status' => 'completed', 'delivery_fee' => 2000,
        ]);
        $this->delivery([
            'delivery_person_name' => 'MUSA BELLO', 'status' => 'dispatched', 'delivery_fee' => 2000,
        ]);
        $this->delivery([
            'delivery_person_name' => 'CHIDI EZE', 'status' => 'completed', 'delivery_fee' => 3000,
        ]);

        $byRider = $this->report()->viewData('byRider')->keyBy('rider');

        $this->assertSame(2, $byRider['MUSA BELLO']['carried']);
        $this->assertSame(1, $byRider['MUSA BELLO']['delivered']);
        $this->assertSame(1, $byRider['MUSA BELLO']['still_out']);
        $this->assertSame(2000.0, $byRider['MUSA BELLO']['fees'], 'only the one that arrived');
        $this->assertSame(3000.0, $byRider['CHIDI EZE']['fees']);
    }

    // -- the window and the filter ---------------------------------------

    public function test_the_date_range_is_respected(): void
    {
        $old = $this->delivery(['delivery_fee' => 2000]);

        // Through the query builder: created_at is not fillable, so update()
        // on the model would drop it and the order would stay in the window.
        Order::where('id', $old->id)->update(['created_at' => now()->subMonths(3)]);

        $this->delivery(['delivery_fee' => 5000]);

        $this->report()->assertViewHas('earned', 5000.0);
    }

    public function test_one_area_can_be_looked_at_on_its_own(): void
    {
        $this->delivery(['delivery_area' => 'Ikeja', 'delivery_fee' => 2000]);
        $this->delivery(['delivery_area' => 'Lekki', 'delivery_fee' => 3500]);

        $this->report()
            ->set('area', 'Lekki')
            ->assertViewHas('totalOrders', 1)
            ->assertViewHas('earned', 3500.0);
    }

    // -- who may read it --------------------------------------------------

    public function test_the_auditor_can_read_it(): void
    {
        // Delivery fees are revenue, and working out what the pharmacy made
        // is what the auditor is here for.
        $this->delivery();

        $this->actingAs($this->user(['auditor']))
            ->get(route('delivery.report'))
            ->assertOk();
    }

    public function test_sales_can_read_it(): void
    {
        // They dispatch; knowing what is still out is their job.
        $this->actingAs($this->user(['sales']))
            ->get(route('delivery.report'))
            ->assertOk();
    }

    public function test_a_cashier_is_not_given_the_delivery_report(): void
    {
        $this->actingAs($this->user(['cashier']))
            ->get(route('delivery.report'))
            ->assertForbidden();
    }

    public function test_the_export_carries_the_same_filter_as_the_screen(): void
    {
        // An export that quietly ignores the filter is worse than no export:
        // it looks like the answer to the question that was asked.
        $this->delivery(['delivery_area' => 'Ikeja', 'delivery_person_name' => 'MUSA BELLO']);
        $this->delivery(['delivery_area' => 'Lekki']);

        $response = $this->report()->set('area', 'Ikeja')->instance()->export();

        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString('Ikeja', $csv);
        $this->assertStringContainsString('MUSA BELLO', $csv);
        $this->assertStringNotContainsString('Lekki', $csv);
    }
}
