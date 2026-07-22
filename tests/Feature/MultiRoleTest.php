<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class MultiRoleTest extends TestCase
{
    use RefreshDatabase;

    // ── Model helpers ────────────────────────────────────────────

    public function test_role_is_persisted_and_reloaded_as_array(): void
    {
        $user = User::factory()->create(['role' => ['admin', 'pharmacist']]);

        $fresh = User::find($user->id);

        $this->assertIsArray($fresh->role);
        $this->assertCount(2, $fresh->role);
        $this->assertContains('admin', $fresh->role);
        $this->assertContains('pharmacist', $fresh->role);
    }

    public function test_has_role_matches_present_roles_and_rejects_absent_ones(): void
    {
        $user = User::factory()->create(['role' => ['pharmacist', 'inventory_manager']]);

        $this->assertTrue($user->hasRole('pharmacist'));
        $this->assertTrue($user->hasRole('inventory_manager'));
        $this->assertFalse($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('cashier'));
    }

    public function test_is_admin_returns_true_only_when_admin_is_in_role_array(): void
    {
        $admin   = User::factory()->create(['role' => ['admin']]);
        $cashier = User::factory()->create(['role' => ['cashier']]);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($cashier->isAdmin());
    }

    public function test_is_branch_manager_returns_true_only_when_role_present(): void
    {
        $manager = User::factory()->create(['role' => ['branch_manager']]);
        $cashier = User::factory()->create(['role' => ['cashier']]);

        $this->assertTrue($manager->isBranchManager());
        $this->assertFalse($cashier->isBranchManager());
    }

    public function test_admin_with_additional_roles_still_passes_is_admin(): void
    {
        $user = User::factory()->create(['role' => ['admin', 'pharmacist']]);

        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->hasRole('pharmacist'));
    }

    // ── Middleware (direct) ───────────────────────────────────────

    public function test_middleware_passes_when_user_has_one_of_the_allowed_roles(): void
    {
        $user = User::factory()->create(['role' => ['pharmacist', 'cashier']]);
        $this->actingAs($user);

        $passed = false;
        (new CheckRole())->handle(
            Request::create('/desk/pos'),
            function () use (&$passed) { $passed = true; return response('ok'); },
            'admin', 'pharmacist', 'branch_manager', 'sales'
        );

        $this->assertTrue($passed);
    }

    public function test_middleware_aborts_403_when_user_has_none_of_the_allowed_roles(): void
    {
        $user = User::factory()->create(['role' => ['cashier']]);
        $this->actingAs($user);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        (new CheckRole())->handle(
            Request::create('/desk/staff'),
            fn() => response('ok'),
            'admin'
        );
    }

    public function test_middleware_passes_for_each_individual_valid_role(): void
    {
        $roles = ['admin', 'pharmacist', 'branch_manager', 'sales', 'cashier', 'inventory_manager'];

        foreach ($roles as $role) {
            $user = User::factory()->create(['role' => [$role]]);
            $this->actingAs($user);

            $passed = false;
            (new CheckRole())->handle(
                Request::create('/desk/some-route'),
                function () use (&$passed) { $passed = true; return response('ok'); },
                ...$roles  // all roles allowed
            );

            $this->assertTrue($passed, "Role '$role' should have passed but was denied.");
        }
    }

    // ── HTTP route access ─────────────────────────────────────────

    public function test_cashier_is_denied_the_admin_only_staff_route(): void
    {
        $cashier = User::factory()->create(['role' => ['cashier']]);

        $this->actingAs($cashier)->get('/desk/staff')->assertForbidden();
    }

    public function test_sales_role_is_denied_the_cashier_route(): void
    {
        $sales = User::factory()->create(['role' => ['sales']]);

        $this->actingAs($sales)->get('/desk/cashier')->assertForbidden();
    }

    public function test_cashier_is_denied_inventory_routes(): void
    {
        $cashier = User::factory()->create(['role' => ['cashier']]);

        $this->actingAs($cashier)->get('/desk/categories')->assertForbidden();
        $this->actingAs($cashier)->get('/desk/inventory')->assertForbidden();
    }

    public function test_pharmacist_is_denied_admin_only_routes(): void
    {
        $pharmacist = User::factory()->create(['role' => ['pharmacist']]);

        $this->actingAs($pharmacist)->get('/desk/staff')->assertForbidden();
        $this->actingAs($pharmacist)->get('/desk/branches')->assertForbidden();
        $this->actingAs($pharmacist)->get('/desk/settings')->assertForbidden();
    }

    public function test_admin_can_access_admin_only_routes(): void
    {
        $admin = User::factory()->create(['role' => ['admin']]);

        $this->actingAs($admin)->get('/desk/staff')->assertOk();
    }

    public function test_inventory_manager_can_access_inventory_routes(): void
    {
        $invManager = User::factory()->create(['role' => ['inventory_manager']]);

        $this->actingAs($invManager)->get('/desk/inventory')->assertOk();
        $this->actingAs($invManager)->get('/desk/categories')->assertOk();
    }

    public function test_inventory_manager_is_denied_sales_routes(): void
    {
        $invManager = User::factory()->create(['role' => ['inventory_manager']]);

        $this->actingAs($invManager)->get('/desk/cashier')->assertForbidden();
    }

    public function test_user_with_multiple_roles_can_access_all_permitted_sections(): void
    {
        // pharmacist can access POS (sales group) AND inventory group
        $user = User::factory()->create(['role' => ['pharmacist']]);

        $this->actingAs($user)->get('/desk/pos')->assertOk();
        $this->actingAs($user)->get('/desk/inventory')->assertOk();
        $this->actingAs($user)->get('/desk/reports')->assertOk();
    }
}
