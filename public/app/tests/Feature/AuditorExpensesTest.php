<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The auditor can read expenses.
 *
 * Money going out is half of working out what the pharmacy actually made, so
 * an auditor who can see revenue but not costs cannot finish the calculation.
 *
 * Reading only. The write guards were already on the component from when this
 * page was previously reachable - the point of these tests is that reaching it
 * again does not quietly hand over the ability to change anything.
 */
class AuditorExpensesTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $roles): User
    {
        return User::factory()->create(['role' => $roles, 'status' => 'active']);
    }

    private function expense(float $amount = 15000): Expense
    {
        return Expense::create([
            'category'    => 'Utilities',
            'description' => 'GENERATOR DIESEL',
            'amount'      => $amount,
            'expense_date' => now()->toDateString(),
            'user_id'     => $this->user(['admin'])->id,
        ]);
    }

    // ── reading ─────────────────────────────────────────────────────────

    public function test_an_auditor_can_open_the_page(): void
    {
        $this->actingAs($this->user(['auditor']))
            ->get(route('expenses.index'))
            ->assertOk();
    }

    public function test_an_auditor_sees_the_expenses(): void
    {
        $this->expense();

        Livewire::actingAs($this->user(['auditor']))
            ->test(\App\Livewire\Expenses\Index::class)
            ->assertOk()
            ->assertSee('GENERATOR DIESEL');
    }

    public function test_it_appears_in_their_menu(): void
    {
        $menu = $this->actingAs($this->user(['auditor']))
            ->get(route('dashboard'))
            ->getContent();

        $this->assertStringContainsString('Expenses', $menu);
    }

    // ── and nothing more ────────────────────────────────────────────────

    public function test_an_auditor_cannot_record_an_expense(): void
    {
        Livewire::actingAs($this->user(['auditor']))
            ->test(\App\Livewire\Expenses\Index::class)
            ->set('category', 'Utilities')
            ->set('description', 'INVENTED')
            ->set('amount', 5000)
            ->set('expense_date', now()->format('Y-m-d'))
            ->call('save');

        $this->assertDatabaseMissing('expenses', ['description' => 'INVENTED']);
    }

    public function test_an_auditor_cannot_delete_an_expense(): void
    {
        // Guarded in the action, not by hiding the button: a Livewire method
        // stays callable whatever the page renders.
        $expense = $this->expense();

        Livewire::actingAs($this->user(['auditor']))
            ->test(\App\Livewire\Expenses\Index::class)
            ->call('delete', $expense->id);

        $this->assertDatabaseHas('expenses', ['id' => $expense->id]);
    }

    public function test_an_auditor_cannot_edit_an_expense(): void
    {
        $expense = $this->expense();

        Livewire::actingAs($this->user(['auditor']))
            ->test(\App\Livewire\Expenses\Index::class)
            ->call('openEdit', $expense->id)
            ->set('amount', 1)
            ->call('save');

        $this->assertEquals(15000, $expense->fresh()->amount);
    }

    public function test_the_record_button_is_not_offered_to_them(): void
    {
        // It did nothing when clicked, with no message - a dead control for
        // cashiers as well as auditors.
        $page = Livewire::actingAs($this->user(['auditor']))
            ->test(\App\Livewire\Expenses\Index::class);

        $this->assertFalse($page->instance()->canManage);
        $page->call('openCreate')->assertSet('modal', false);
    }

    public function test_a_cashier_is_not_offered_it_either(): void
    {
        $page = Livewire::actingAs($this->user(['cashier']))
            ->test(\App\Livewire\Expenses\Index::class);

        $this->assertFalse($page->instance()->canManage);
    }

    public function test_a_branch_manager_still_can(): void
    {
        $page = Livewire::actingAs($this->user(['branch_manager']))
            ->test(\App\Livewire\Expenses\Index::class);

        $this->assertTrue($page->instance()->canManage);
        $page->call('openCreate')->assertSet('modal', true);
    }

    public function test_someone_holding_both_roles_keeps_their_authority(): void
    {
        // An auditor who is also a branch manager is not made read-only by the
        // auditor tag alone.
        Livewire::actingAs($this->user(['auditor', 'branch_manager']))
            ->test(\App\Livewire\Expenses\Index::class)
            ->set('category', 'Utilities')
            ->set('description', 'LEGITIMATE')
            ->set('amount', 5000)
            ->set('expense_date', now()->format('Y-m-d'))
            ->call('save');

        $this->assertDatabaseHas('expenses', ['description' => 'LEGITIMATE']);
    }
}
