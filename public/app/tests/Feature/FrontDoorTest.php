<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The front door of the staff app.
 *
 * There is no public landing page here - that is the shop, on its own domain.
 * Anyone who arrives at the root is a member of staff who needs to sign in.
 */
class FrontDoorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_at_the_root_is_sent_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_nothing_of_the_business_is_visible_before_signing_in(): void
    {
        // The redirect above is a convenience, not the guard. Named pages must
        // refuse a stranger on their own.
        foreach (['pos.index', 'inventory.index', 'reports.index'] as $page) {
            $this->get(route($page))->assertRedirect('/login');
        }
    }

    public function test_a_signed_in_member_of_staff_gets_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'active']))
            ->get(route('dashboard'))
            ->assertOk();
    }
}
