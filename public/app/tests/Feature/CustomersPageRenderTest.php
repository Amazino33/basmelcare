<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The customers page renders per-row action buttons inside a MaryUI @scope
 * block. Those blocks do NOT inherit variables passed from render(), so a
 * role flag resolved in the component and used inside @scope blows up with
 * "Undefined variable" for every user. This guards that regression.
 */
class CustomersPageRenderTest extends TestCase
{
    use RefreshDatabase;

    private function url(): string
    {
        return '/' . trim(config('app.desk_prefix'), '/') . '/customers';
    }

    public static function roleProvider(): array
    {
        return [
            'admin'             => [['admin']],
            'pharmacist'        => [['pharmacist']],
            'branch_manager'    => [['branch_manager']],
            'sales'             => [['sales']],
            'cashier'           => [['cashier']],
            'promoter'          => [['promoter']],
            'admin+branch_mgr'  => [['admin', 'branch_manager']],
        ];
    }

    /**
     * A row must exist, otherwise the table renders empty and the @scope block
     * — where the bug lives — never executes.
     */
    private function seedCustomer(?User $registeredBy = null): Customer
    {
        return Customer::create([
            'name'          => 'Aisha Bello',
            'type'          => 'retail',
            'phone'         => '0803' . random_int(1000000, 9999999),
            'registered_by' => $registeredBy?->id,
        ]);
    }

    #[DataProvider('roleProvider')]
    public function test_customers_page_renders_for_every_role_with_access(array $roles): void
    {
        $user = User::factory()->create(['role' => $roles, 'status' => 'active']);
        $this->seedCustomer($user);

        $response = $this->actingAs($user)->get($this->url());

        $response->assertOk();
        $this->assertStringNotContainsString('Undefined variable', $response->getContent());
        // Proves the @scope block actually ran for this role.
        $response->assertSee('viewProfile(', false);
    }

    public function test_non_promoter_sees_edit_and_delete_actions(): void
    {
        $user = User::factory()->create(['role' => ['branch_manager'], 'status' => 'active']);
        $this->seedCustomer($user);

        $response = $this->actingAs($user)->get($this->url());

        $response->assertOk();
        $response->assertSee('viewProfile(', false);
        $response->assertSee('wire:click="edit(', false);
        $response->assertSee('wire:click="delete(', false);
    }

    public function test_promoter_cannot_see_edit_or_delete_actions(): void
    {
        $user = User::factory()->create(['role' => ['promoter'], 'status' => 'active']);
        // Promoters only see customers they registered, so attribute it to them.
        $this->seedCustomer($user);

        $response = $this->actingAs($user)->get($this->url());

        $response->assertOk();
        $this->assertStringNotContainsString('Undefined variable', $response->getContent());
        $response->assertSee('viewProfile(', false);
        $response->assertDontSee('wire:click="edit(', false);
        $response->assertDontSee('wire:click="delete(', false);
    }
}
