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

    public function test_a_cashier_is_offered_it(): void
    {
        // She is the one handing over the money for transport, diesel or a
        // repair, and this page has always been on her route. She was left off
        // canManage by accident: the button did nothing when she clicked it,
        // and hiding a dead control was mistaken for deciding she should not
        // have one.
        $page = Livewire::actingAs($this->user(['cashier']))
            ->test(\App\Livewire\Expenses\Index::class);

        $this->assertTrue($page->instance()->canManage);

        $page->call('openCreate')->assertSet('modal', true);
    }

    public function test_a_cashier_can_actually_record_one(): void
    {
        Livewire::actingAs($this->user(['cashier']))
            ->test(\App\Livewire\Expenses\Index::class)
            ->call('openCreate')
            ->set('category', 'Transport')
            ->set('description', 'KEKE TO WHOLESALER')
            ->set('amount', 2500)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, Expense::where('description', 'KEKE TO WHOLESALER')->count());
    }

    public function test_a_cashier_can_correct_a_figure_she_mistyped(): void
    {
        $expense = $this->expense(15000);

        Livewire::actingAs($this->user(['cashier']))
            ->test(\App\Livewire\Expenses\Index::class)
            ->call('openEdit', $expense->id)
            ->assertSet('modal', true)
            ->set('amount', 1500)
            ->call('save');

        $this->assertEquals(1500, $expense->fresh()->amount);
    }

    public function test_a_cashier_cannot_delete_an_expense(): void
    {
        // Recording and deleting are different powers. An expense is the record
        // that money left the till; removing one removes the evidence, so it
        // stays with management.
        $expense = $this->expense();

        Livewire::actingAs($this->user(['cashier']))
            ->test(\App\Livewire\Expenses\Index::class)
            ->call('delete', $expense->id);

        $this->assertDatabaseHas('expenses', ['id' => $expense->id]);
    }

    public function test_the_delete_control_is_not_offered_to_a_cashier(): void
    {
        $page = Livewire::actingAs($this->user(['cashier']))
            ->test(\App\Livewire\Expenses\Index::class);

        $this->assertFalse($page->instance()->canDelete);
    }

    public function test_saving_is_governed_by_the_same_rule_as_opening(): void
    {
        // These were two different lists: the open guards checked canManage and
        // save checked only the auditor trait, so whoever called save directly
        // was judged by a different rule from whoever clicked the button.
        $expense = $this->expense(15000);

        Livewire::actingAs($this->user(['auditor']))
            ->test(\App\Livewire\Expenses\Index::class)
            ->set('editId', $expense->id)
            ->set('category', 'Utilities')
            ->set('description', 'CHANGED')
            ->set('amount', 1)
            ->set('expense_date', now()->toDateString())
            ->call('save');

        $this->assertEquals(15000, $expense->fresh()->amount);
    }

    public function test_a_changed_figure_leaves_a_trail(): void
    {
        // Widening who can edit a money record is only safe if the edit is
        // traceable. Nothing recorded who changed an expense before.
        $expense = $this->expense(15000);

        Livewire::actingAs($this->user(['cashier']))
            ->test(\App\Livewire\Expenses\Index::class)
            ->call('openEdit', $expense->id)
            ->set('amount', 40000)
            ->call('save');

        $entry = \App\Models\AuditLog::where('field', 'amount')->latest('id')->first();

        $this->assertNotNull($entry, 'An expense was edited with nothing recording it.');
        $this->assertEquals(15000, $entry->old_value);
        $this->assertEquals(40000, $entry->new_value);
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
