<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\StockMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fill in the before/after balances for movements recorded before the app
 * started keeping them.
 *
 * Not a replay of the log, which would be a guess - batch quantities can also
 * be corrected directly, and those corrections are not movements, so a replay
 * would drift silently wherever one happened.
 *
 * Instead it reads what was actually recorded at the time. Batch audits its
 * quantity, so every change already carries a before and an after in the money
 * trail. A movement is matched to its audit entry only when all three agree:
 * the same batch, within a few seconds of each other, and the audit's own
 * change equal to the movement's quantity. Anything that does not match all
 * three is left null and reported, rather than filled with something plausible.
 */
class BackfillStockBalances extends Command
{
    protected $signature = 'stock:backfill-balances
                            {--seconds=10 : How far apart a movement and its audit entry may be}
                            {--dry-run : Report what would be filled without writing}';

    protected $description = 'Recover before/after stock balances for older movements from the money trail';

    public function handle(): int
    {
        $window = (int) $this->option('seconds');
        $dry    = (bool) $this->option('dry-run');

        $pending = StockMovement::whereNull('balance_after')->count();

        if ($pending === 0) {
            $this->info('Every movement already carries a balance. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info("{$pending} movements have no balance recorded.");

        $filled = 0;
        $missed = 0;
        $bar    = $this->output->createProgressBar($pending);

        StockMovement::whereNull('balance_after')
            ->orderBy('id')
            ->chunkById(500, function ($movements) use (&$filled, &$missed, $window, $dry, $bar) {
                foreach ($movements as $movement) {
                    // Compared in PHP rather than in SQL: casting a text column
                    // to a number is spelled differently in MySQL and SQLite,
                    // and this runs against both. The window keeps the set tiny.
                    $entry = AuditLog::where('auditable_type', Batch::class)
                        ->where('auditable_id', (string) $movement->batch_id)
                        ->where('field', 'quantity')
                        ->whereBetween('created_at', [
                            $movement->created_at->copy()->subSeconds($window),
                            $movement->created_at->copy()->addSeconds($window),
                        ])
                        ->orderBy('id')
                        ->get()
                        // The audit's own change must be this movement's change.
                        // Without it, two movements on one batch seconds apart
                        // would each match the other's entry.
                        ->first(fn ($row) => (int) $row->new_value - (int) $row->old_value === (int) $movement->quantity);

                    if (! $entry) {
                        $missed++;
                        $bar->advance();
                        continue;
                    }

                    if (! $dry) {
                        $movement->forceFill(['balance_after' => (int) $entry->new_value])->saveQuietly();
                    }

                    $filled++;
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);

        $this->info(($dry ? 'Would fill ' : 'Filled ') . $filled . ' from the money trail.');

        if ($missed > 0) {
            $this->warn($missed . ' could not be matched and stay blank. Those predate the audit trail, '
                . 'or their batch was changed by something other than the movement itself.');
        }

        return self::SUCCESS;
    }
}
