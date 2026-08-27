<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A record saved with no branch is invisible to everyone who has one.
 *
 * The branch scope filters to the viewer's branch when they have one, and
 * does nothing when they do not. So a cashier left without a branch sees
 * every branch's records - and everything she records carries no branch, and
 * disappears for every colleague who does have one. She becomes a hole rather
 * than a supervisor, and nothing on screen explains it.
 *
 * Two halves: nothing new may be saved without a branch, and nothing new may
 * create a user who would cause it.
 */
class BranchAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function branches(): array
    {
        return [
            Branch::create(['name' => 'MAIN BRANCH', 'is_main' => true]),
            Branch::create(['name' => 'IKEJA BRANCH', 'is_main' => false]),
        ];
    }

    private function user(array $roles, ?int $branchId): User
    {
        return User::factory()->create([
            'role'      => $roles,
            'status'    => 'active',
            'branch_id' => $branchId,
        ]);
    }

    private function recordExpense(User $as, string $description): Expense
    {
        $this->actingAs($as);

        return Expense::create([
            'category'     => 'Utilities',
            'description'  => $description,
            'amount'       => 5000,
            'expense_date' => now()->toDateString(),
            'user_id'      => $as->id,
        ]);
    }

    // ── nothing is saved without a branch ───────────────────────────────

    public function test_a_record_takes_the_users_branch(): void
    {
        [$main] = $this->branches();

        $expense = $this->recordExpense($this->user(['cashier'], $main->id), 'DIESEL');

        $this->assertSame($main->id, $expense->branch_id);
    }

    public function test_a_branchless_user_no_longer_creates_an_invisible_record(): void
    {
        // This is the bug: her expenses used to save with no branch and vanish
        // for the cashier who had one.
        [$main] = $this->branches();

        $expense = $this->recordExpense($this->user(['cashier'], null), 'DIESEL');

        $this->assertSame($main->id, $expense->branch_id);
    }

    public function test_it_falls_back_to_the_main_branch_specifically(): void
    {
        $second = Branch::create(['name' => 'IKEJA BRANCH', 'is_main' => false]);
        $main   = Branch::create(['name' => 'MAIN BRANCH', 'is_main' => true]);

        // Not simply the first branch by id - the one marked main.
        $expense = $this->recordExpense($this->user(['cashier'], null), 'DIESEL');

        $this->assertSame($main->id, $expense->branch_id);
        $this->assertNotSame($second->id, $expense->branch_id);
    }

    public function test_an_explicit_branch_is_never_overridden(): void
    {
        [$main, $ikeja] = $this->branches();

        $cashier = $this->user(['cashier'], $main->id);
        $this->actingAs($cashier);

        $expense = Expense::create([
            'category'     => 'Utilities',
            'description'  => 'DIESEL',
            'amount'       => 5000,
            'expense_date' => now()->toDateString(),
            'user_id'      => $cashier->id,
            'branch_id'    => $ikeja->id,
        ]);

        $this->assertSame($ikeja->id, $expense->branch_id);
    }

    // ── colleagues can now see each other's work ────────────────────────

    public function test_two_cashiers_in_one_branch_see_the_same_expenses(): void
    {
        // The complaint that started this.
        [$main] = $this->branches();

        $withBranch    = $this->user(['cashier'], $main->id);
        $withoutBranch = $this->user(['cashier'], null);

        $this->recordExpense($withBranch, 'RECORDED BY BOLA');
        $this->recordExpense($withoutBranch, 'RECORDED BY VIVIANE');

        $this->actingAs($withBranch);

        $this->assertSame(2, Expense::count(), 'One cashier still cannot see the other.');
    }

    // ── the cause is refused at the source ──────────────────────────────

    private function staffForm(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::actingAs($this->user(['admin'], null))
            ->test(\App\Livewire\Staff\Index::class);
    }

    public function test_a_cashier_cannot_be_created_without_a_branch(): void
    {
        $this->branches();

        $this->staffForm()
            ->set('name', 'VIVIANE')
            ->set('email', 'viviane@example.com')
            ->set('role', ['cashier'])
            ->set('branch_id', null)
            ->call('save')
            ->assertHasErrors('branch_id');
    }

    public function test_an_admin_may_be_left_without_one(): void
    {
        // An admin sees every branch by design, so branchless is correct there.
        $this->branches();

        $this->staffForm()
            ->set('name', 'DR BASMEL')
            ->set('email', 'basmel@example.com')
            ->set('role', ['admin'])
            ->set('branch_id', null)
            ->call('save')
            ->assertHasNoErrors('branch_id');
    }

    // ── the clean-up command ────────────────────────────────────────────

    private function orphanedExpense(): Expense
    {
        // Saved the old way, before the fallback existed.
        $expense = $this->recordExpense($this->user(['cashier'], null), 'OLD AND INVISIBLE');
        $expense->forceFill(['branch_id' => null])->saveQuietly();

        return $expense;
    }

    public function test_the_command_reports_records_with_no_branch(): void
    {
        $this->branches();
        $this->orphanedExpense();

        Artisan::call('branch:assign');

        $this->assertStringContainsString('expenses', Artisan::output());
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        [$main] = $this->branches();
        $expense = $this->orphanedExpense();

        Artisan::call('branch:assign', ['--to' => $main->id, '--dry-run' => true]);

        $this->assertNull($expense->fresh()->branch_id);
    }

    public function test_it_assigns_when_told_to(): void
    {
        [$main] = $this->branches();
        $expense = $this->orphanedExpense();

        Artisan::call('branch:assign', ['--to' => $main->id]);

        $this->assertSame($main->id, $expense->fresh()->branch_id);
    }

    public function test_it_refuses_a_branch_that_does_not_exist(): void
    {
        $this->branches();
        $expense = $this->orphanedExpense();

        $this->assertSame(1, Artisan::call('branch:assign', ['--to' => 9999]));
        $this->assertNull($expense->fresh()->branch_id);
    }

    public function test_it_names_the_users_causing_it(): void
    {
        // The symptom is the record; the cause is a person with no branch.
        $this->branches();
        User::factory()->create(['role' => ['cashier'], 'status' => 'active', 'branch_id' => null, 'name' => 'VIVIANE']);

        Artisan::call('branch:assign');

        $this->assertStringContainsString('VIVIANE', Artisan::output());
    }
}
