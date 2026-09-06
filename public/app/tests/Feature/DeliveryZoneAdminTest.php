<?php

namespace Tests\Feature;

use App\Livewire\Delivery\Zones;
use App\Models\AppSetting;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Who sets what the shop charges to deliver.
 *
 * This is pricing, so it sits with the people who set product prices: admin
 * and branch manager. Sales can read it - dispatch needs to know what an area
 * covers - but a delivery charge is not theirs to move, and the guard is on
 * the action rather than on the button, because a Livewire method stays
 * callable whether or not the control that calls it was rendered.
 */
class DeliveryZoneAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The migration carries the shop's existing flat fee in as one zone.
        // These tests are about a pharmacy setting its own up.
        DeliveryZone::query()->delete();
    }

    private function user(array $roles): User
    {
        return User::factory()->create(['role' => $roles, 'status' => 'active']);
    }

    private function zone(string $name = 'Ikeja', float $fee = 2000): DeliveryZone
    {
        return DeliveryZone::create([
            'name' => $name, 'fee' => $fee, 'is_active' => true, 'sort_order' => 0,
        ]);
    }

    // -- who may change a charge -----------------------------------------

    public function test_a_manager_can_add_an_area(): void
    {
        Livewire::actingAs($this->user(['branch_manager']))
            ->test(Zones::class)
            ->call('create')
            ->set('name', 'Lekki Phase 1')
            ->set('fee', '3500')
            ->set('note', 'Same day if ordered before 4pm')
            ->call('save')
            ->assertHasNoErrors();

        $zone = DeliveryZone::where('name', 'Lekki Phase 1')->first();

        $this->assertNotNull($zone);
        $this->assertSame(3500.0, (float) $zone->fee);
        $this->assertTrue($zone->is_active);
    }

    public function test_sales_cannot_change_what_delivery_costs(): void
    {
        $zone = $this->zone('Ikeja', 2000);

        Livewire::actingAs($this->user(['sales']))
            ->test(Zones::class)
            ->call('edit', $zone->id)
            ->set('name', 'Ikeja')
            ->set('fee', '50')
            ->call('save');

        $this->assertSame(2000.0, (float) $zone->fresh()->fee, 'the charge is unchanged');
        $this->assertSame(1, DeliveryZone::count(), 'and nothing new was created');
    }

    public function test_sales_cannot_turn_an_area_off(): void
    {
        // Refusing to deliver somewhere is a commercial decision, not a
        // dispatch one.
        $zone = $this->zone();

        Livewire::actingAs($this->user(['sales']))
            ->test(Zones::class)
            ->call('toggleActive', $zone->id);

        $this->assertTrue($zone->fresh()->is_active);
    }

    public function test_sales_cannot_remove_an_area(): void
    {
        $zone = $this->zone();

        Livewire::actingAs($this->user(['sales']))
            ->test(Zones::class)
            ->call('delete', $zone->id);

        $this->assertNotNull($zone->fresh());
    }

    public function test_sales_can_still_see_the_areas(): void
    {
        $this->zone('Ikeja', 2000);

        Livewire::actingAs($this->user(['sales']))
            ->test(Zones::class)
            ->assertOk()
            ->assertSee('Ikeja');
    }

    // -- history is not rewritten ----------------------------------------

    public function test_an_area_that_has_taken_orders_cannot_be_removed(): void
    {
        // Deleting it would leave the shop unable to say where a delivery
        // went. Turning it off is what "we do not go there any more" means.
        $zone = $this->zone();

        Order::create([
            'order_number'     => 'ORD-TEST-1',
            'subtotal'         => 4000,
            'delivery_fee'     => 2000,
            'delivery_zone_id' => $zone->id,
            'delivery_area'    => $zone->name,
            'total_amount'     => 6000,
            'fulfillment_type' => 'delivery',
            'payment_method'   => 'pay_on_delivery',
            'payment_status'   => 'pending',
            'status'           => 'pending',
        ]);

        Livewire::actingAs($this->user(['admin']))
            ->test(Zones::class)
            ->call('delete', $zone->id);

        $this->assertNotNull($zone->fresh(), 'the area survives');
    }

    public function test_turning_an_area_off_takes_it_off_the_checkout_but_keeps_it(): void
    {
        $zone = $this->zone();

        Livewire::actingAs($this->user(['admin']))
            ->test(Zones::class)
            ->call('toggleActive', $zone->id);

        $this->assertFalse($zone->fresh()->is_active);
        $this->assertSame(0, DeliveryZone::active()->count());
        $this->assertSame(1, DeliveryZone::count());
    }

    // -- the shop-wide settings ------------------------------------------

    public function test_a_manager_can_switch_delivery_off_altogether(): void
    {
        $this->zone();

        Livewire::actingAs($this->user(['admin']))
            ->test(Zones::class)
            ->set('delivery_enabled', false)
            ->call('saveSettings')
            ->assertHasNoErrors();

        $this->assertFalse(AppSetting::bool('delivery_enabled', true));
        $this->assertFalse(DeliveryZone::deliveryOffered());
    }

    public function test_a_manager_can_set_the_free_delivery_threshold(): void
    {
        Livewire::actingAs($this->user(['admin']))
            ->test(Zones::class)
            ->set('delivery_free_over', '20000')
            ->call('saveSettings')
            ->assertHasNoErrors();

        $this->assertSame(20000.0, DeliveryZone::freeOver());
    }

    public function test_sales_cannot_change_the_shop_wide_settings(): void
    {
        AppSetting::set('delivery_free_over', 20000);

        Livewire::actingAs($this->user(['sales']))
            ->test(Zones::class)
            ->set('delivery_free_over', '0')
            ->call('saveSettings');

        $this->assertSame(20000.0, DeliveryZone::freeOver());
    }

    public function test_a_charge_cannot_be_negative(): void
    {
        Livewire::actingAs($this->user(['admin']))
            ->test(Zones::class)
            ->call('create')
            ->set('name', 'Nowhere')
            ->set('fee', '-500')
            ->call('save')
            ->assertHasErrors('fee');

        $this->assertSame(0, DeliveryZone::count());
    }

    public function test_free_delivery_to_an_area_is_allowed(): void
    {
        // Zero is a real answer - the street the pharmacy is on.
        Livewire::actingAs($this->user(['admin']))
            ->test(Zones::class)
            ->call('create')
            ->set('name', 'Our own street')
            ->set('fee', '0')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(0.0, (float) DeliveryZone::where('name', 'Our own street')->first()->fee);
    }
}
