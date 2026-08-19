<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every role reaches the dashboard, so a variable missing from one panel takes
 * the whole app's landing page down. Also guards the mobile-first stat layout:
 * MaryUI's x-stat clips titles and overflows currency values on a phone unless
 * the compact overrides are applied.
 */
class DashboardRenderTest extends TestCase
{
    use RefreshDatabase;

    private function url(): string
    {
        return '/' . trim(config('app.desk_prefix'), '/') . '/dashboard';
    }

    public static function roleProvider(): array
    {
        return [
            'admin'              => [['admin']],
            'pharmacist'         => [['pharmacist']],
            'branch_manager'     => [['branch_manager']],
            'sales'              => [['sales']],
            'cashier'            => [['cashier']],
            'inventory_manager'  => [['inventory_manager']],
            'promoter'           => [['promoter']],
            'content'            => [['content']],
            'promoter+content'   => [['promoter', 'content']],
            'inventory+content'  => [['inventory_manager', 'content']],
            'admin+branch_mgr'   => [['admin', 'branch_manager']],
        ];
    }

    #[DataProvider('roleProvider')]
    public function test_dashboard_renders_for_every_role(array $roles): void
    {
        $user = User::factory()->create(['role' => $roles, 'status' => 'active']);

        $response = $this->actingAs($user)->get($this->url());

        $response->assertOk();
        $this->assertStringNotContainsString('Undefined variable', $response->getContent());
    }

    #[DataProvider('roleProvider')]
    public function test_stat_cards_are_compacted_for_mobile(array $roles): void
    {
        $user = User::factory()->create(['role' => $roles, 'status' => 'active']);

        // Blade escapes & in attributes, so [&_svg] renders as [&amp;_svg].
        $html = html_entity_decode(
            $this->actingAs($user)->get($this->url())->getContent()
        );

        // Titles must be able to wrap, or they clip to "Reg…" on a phone.
        $this->assertStringContainsString('[&_.whitespace-nowrap]:whitespace-normal', $html);
        // Icon and padding must shrink below sm, or currency values overflow.
        $this->assertStringContainsString('[&_svg]:w-7', $html);
        $this->assertStringContainsString('px-3 py-3 sm:px-5 sm:py-4', $html);
    }

    public function test_no_stat_grid_is_locked_to_three_columns_on_mobile(): void
    {
        $user = User::factory()->create(['role' => ['promoter'], 'status' => 'active']);

        $html = $this->actingAs($user)->get($this->url())->getContent();

        // grid-cols-3 with no responsive prefix is what squeezed three cards
        // onto a 360px screen. It must always be gated behind a breakpoint.
        $this->assertDoesNotMatchRegularExpression(
            '/class="[^"]*(?<![a-z:])grid-cols-3[^"]*"/',
            $html,
            'Found an unresponsive grid-cols-3 on the promoter dashboard.'
        );
        $this->assertStringContainsString('grid-cols-2 sm:grid-cols-3', $html);
    }
}
