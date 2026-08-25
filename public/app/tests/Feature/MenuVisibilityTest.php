<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The menu items have to be in the layout that actually renders.
 *
 * There are two near-identical layout files. Livewire renders
 * resources/views/layouts/app.blade.php; components/layouts/app.blade.php is a
 * stale copy nobody had touched in months. Menu items added to the stale one
 * are invisible, with nothing failing to say so - which is what happened to
 * the Prescriptions links.
 *
 * These assert against a rendered page rather than a file, so it cannot
 * happen again quietly.
 */
class MenuVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function menuFor(array $roles): string
    {
        $user = User::factory()->create(['role' => $roles, 'status' => 'active']);

        return $this->actingAs($user)->get(route('dashboard'))->getContent();
    }

    public function test_a_pharmacist_sees_both_prescription_pages(): void
    {
        $menu = $this->menuFor(['pharmacist']);

        $this->assertStringContainsString('Prescriptions', $menu);
        $this->assertStringContainsString('Prescription Medicines', $menu);
    }

    public function test_sales_staff_see_the_queue_but_not_the_drug_list(): void
    {
        // They prepare the orders, but marking a drug prescription-only is a
        // clinical decision.
        $menu = $this->menuFor(['sales']);

        $this->assertStringContainsString('Prescriptions', $menu);
        $this->assertStringNotContainsString('Prescription Medicines', $menu);
    }

    public function test_a_cashier_sees_neither(): void
    {
        $menu = $this->menuFor(['cashier']);

        $this->assertStringNotContainsString('Prescription Medicines', $menu);
    }

    public function test_stock_received_is_reachable_from_the_menu(): void
    {
        $this->assertStringContainsString('Stock Received', $this->menuFor(['inventory_manager']));
    }

    public function test_the_stale_layout_carries_no_menu_of_its_own(): void
    {
        // If someone adds an item there it will never appear. Keep it empty of
        // links so the mistake is at least obvious next time.
        $live  = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $stale = file_get_contents(resource_path('views/components/layouts/app.blade.php'));

        $this->assertStringContainsString("route('prescriptions.index')", $live,
            'The prescription links are not in the layout that renders.');
        $this->assertStringNotContainsString("route('prescriptions.index')", $stale,
            'Menu items were added to the stale layout again.');
    }
}
