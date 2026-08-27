<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\FailedSearch;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Finds and fixes records saved with no branch.
 *
 * A branch-scoped record with a null branch is invisible to everyone who has
 * a branch and visible to everyone who does not - so the same expense shows
 * for one cashier and not the other, with nothing on screen to explain why.
 *
 * That happens when a user has no branch themselves: whatever they record
 * inherits nothing. Newer records fall back to the main branch, but anything
 * saved before that needs assigning, and only a person can say to which.
 */
class BranchAssign extends Command
{
    protected $signature = 'branch:assign
                            {--to= : Branch id to assign unassigned records to}
                            {--only= : Limit to one type: expenses, sales, purchase-orders, locations, searches}
                            {--dry-run : Show what would change without changing it}';

    protected $description = 'Report records saved with no branch, and optionally assign them';

    /** Roles that legitimately have no branch: they record nothing scoped to one. */
    private const SPANS_BRANCHES = ['admin', 'auditor', 'content', 'promoter'];

    /** Every model the branch scope applies to. */
    private function models(): array
    {
        return [
            'expenses'        => Expense::class,
            'sales'           => Sale::class,
            'purchase-orders' => PurchaseOrder::class,
            'locations'       => Location::class,
            'searches'        => FailedSearch::class,
        ];
    }

    public function handle(): int
    {
        $this->warnIfNoMainBranch();
        $this->reportUsers();

        $models = $this->models();

        if ($only = $this->option('only')) {
            if (! isset($models[$only])) {
                $this->error('Unknown type: ' . $only);
                $this->line('One of: ' . implode(', ', array_keys($models)));

                return self::FAILURE;
            }

            $models = [$only => $models[$only]];
        }

        $this->newLine();
        $this->info('Records with no branch');

        $total = 0;

        foreach ($models as $label => $class) {
            // withoutGlobalScopes, or the scope hides the very rows being counted.
            $count = $class::withoutGlobalScopes()->whereNull('branch_id')->count();
            $total += $count;

            $this->line(sprintf('  %-18s %d', $label, $count));
        }

        if ($total === 0) {
            $this->newLine();
            $this->info('Nothing is unassigned.');

            return self::SUCCESS;
        }

        $to = $this->option('to');

        if (! $to) {
            $this->newLine();
            $this->line('To assign them, name a branch:');
            $this->line('  php artisan branch:assign --to=1 --dry-run');
            $this->newLine();
            $this->line('Branches:');

            foreach (Branch::orderBy('id')->get() as $branch) {
                $this->line(sprintf('  %-4s %s%s', $branch->id, $branch->name, $branch->is_main ? '  (main)' : ''));
            }

            return self::SUCCESS;
        }

        if (! Branch::whereKey($to)->exists()) {
            $this->error('No branch with id ' . $to);

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->newLine();
        $this->info(($dryRun ? 'Would assign' : 'Assigning') . ' to branch ' . $to);

        foreach ($models as $label => $class) {
            $query = $class::withoutGlobalScopes()->whereNull('branch_id');
            $count = $query->count();

            if ($count === 0) {
                continue;
            }

            if (! $dryRun) {
                // A plain update: no model events, so nothing is re-stamped
                // with today's timestamps or re-audited as a real edit.
                $query->update(['branch_id' => $to]);
            }

            $this->line(sprintf('  %-18s %d', $label, $count));
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn('Nothing was changed. Drop --dry-run to apply.');
        } else {
            $this->info('Done. Users without a branch will keep producing unassigned records.');
        }

        return self::SUCCESS;
    }

    /**
     * The fallback prefers the branch marked main and settles for the lowest
     * id otherwise. With one branch that lands correctly either way, but it is
     * luck rather than intent, and stops being true the moment a second branch
     * is added.
     */
    private function warnIfNoMainBranch(): void
    {
        if (Branch::count() === 0 || Branch::where('is_main', true)->exists()) {
            return;
        }

        $this->warn('No branch is marked as the main one.');
        $this->line('  Records saved without a branch fall back to the lowest id instead.');
        $this->line('  Set one in Branches so that stays deliberate.');
        $this->newLine();
    }

    /**
     * The cause, not just the symptom. Someone who records branch-scoped work
     * without a branch produces records nobody else can see.
     */
    private function reportUsers(): void
    {
        $branchless = User::whereNull('branch_id')->get(['id', 'name', 'role']);

        if ($branchless->isEmpty()) {
            $this->info('Every user has a branch.');

            return;
        }

        $this->warn('Users with no branch:');

        foreach ($branchless as $user) {
            $roles = is_array($user->role) ? implode(', ', $user->role) : (string) $user->role;

            // Only flag those who actually record branch-scoped work. An admin
            // oversees all branches, an auditor only reads, content uploads
            // images and a promoter registers customers - none of that is
            // branch-scoped, so having no branch is correct for them.
            $recordsBranchWork = (bool) array_diff(
                is_array($user->role) ? $user->role : [],
                self::SPANS_BRANCHES,
            );

            $note = $recordsBranchWork ? '   <- their records fall back to the main branch' : '';

            $this->line(sprintf('  %-24s %-30s%s', $user->name, $roles, $note));
        }
    }
}
