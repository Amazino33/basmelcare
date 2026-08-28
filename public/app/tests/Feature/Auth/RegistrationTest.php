<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Nobody signs themselves up.
 *
 * A staff account carries a role, a branch, and access to what customers
 * bought - so it is created by an admin on the Staff page, never by whoever
 * finds the address. The Breeze registration screen this file once tested was
 * unrouted for that reason.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_there_is_no_registration_page(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_no_route_leads_to_the_registration_screen(): void
    {
        // Asserting on the route table as well, because the component file is
        // still on disk: re-adding Breeze's auth routes would quietly reopen
        // public sign-up, and this is what would notice.
        $registration = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'register'))
            ->map(fn ($route) => $route->uri())
            ->values()
            ->all();

        $this->assertSame([], $registration, 'A registration route is reachable: ' . implode(', ', $registration));
    }

    public function test_an_account_still_has_to_come_from_somewhere(): void
    {
        // The counterpart: accounts exist, they are just made by an admin.
        $this->assertSame(0, User::count());

        User::factory()->create(['role' => ['cashier'], 'status' => 'active']);

        $this->assertSame(1, User::count());
    }
}
