<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Console\Command;

/**
 * Reconcile one product: what its batches hold against what the log says they
 * should hold.
 *
 * Written for a real question - a return was processed before the app recorded
 * running balances, and nobody could tell afterwards whether the stock had
 * actually moved. This answers it from the two records that do exist: the
 * movement log, and the money trail's before/after on every batch quantity.
 *
 * It never changes anything. A stock figure is corrected through Adjustments,
 * by a person, with a reason attached - not silently by a script.
 *
 * The awkward part, stated rather than hidden: a batch's quantity can also be
 * changed directly through batch correction, and those changes are not
 * movements. So the movement log alone cannot prove a figure right. Where the
 * money trail has an entry the two are compared and the verdict is firm; where
 * it does not, the command says the check is inconclusive instead of implying
 * a shortfall that may not exist.
 */
class CheckProductStock extends Command
{
    protected $signature = 'stock:check
                            {product : Product name, or part of it, or its id}
                            {--since= : Only count movements from this date (YYYY-MM-DD)}';

    protected $description = 'Compare what a product holds against every sale, return and adjustment recorded for it';

    public function handle(): int
    {
        $needle = (string) $this->argument('product');

        $products = Product::query()
            ->when(is_numeric($needle), fn ($q) => $q->where('id', $needle))
            ->when(! is_numeric($needle), fn ($q) => $q->where('name', 'like', "%{$needle}%"))
            ->with(['batches' => fn ($q) => $q->orderBy('expiry_date')])
            ->get();

        if ($products->isEmpty()) {
            $this->error("No product matches \"{$needle}\".");

            return self::FAILURE;
        }

        if ($products->count() > 8) {
            $this->error($products->count() . ' products match. Be more specific.');

            return self::FAILURE;
        }

        foreach ($products as $product) {
            $this->report($product);
        }

        return self::SUCCESS;
    }

    private function report(Product $product): void
    {
        $this->newLine();
        $this->line('<options=bold>' . $product->name . '</>');
        $this->line(str_repeat('─', min(72, max(20, strlen($product->name)))));

        if ($product->batches->isEmpty()) {
            $this->warn('  No batches. Nothing has ever been stocked.');

            return;
        }

        $since = $this->option('since') ? \Carbon\Carbon::parse($this->option('since'))->startOfDay() : null;

        $onHand = 0;

        foreach ($product->batches as $batch) {
            $onHand += (int) $batch->quantity;
            $this->reportBatch($batch, $since);
        }

        $this->newLine();
        $this->line('  <options=bold>Total on hand across every batch: ' . $onHand . '</>');
    }

    private function reportBatch(Batch $batch, ?\Carbon\Carbon $since): void
    {
        $movements = StockMovement::where('batch_id', $batch->id)
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->orderBy('id')
            ->get();

        $this->newLine();
        $this->line("  Batch <options=bold>{$batch->batch_number}</> — holds <options=bold>{$batch->quantity}</> now");

        if ($movements->isEmpty()) {
            $this->line('    Nothing recorded against it.');

            return;
        }

        // What each kind of movement did, which is the "total sales" question.
        $byType = $movements->groupBy('type')->map(fn ($rows) => [
            'count' => $rows->count(),
            'units' => (int) $rows->sum('quantity'),
        ]);

        $rows = [];
        foreach ($byType as $type => $sums) {
            $rows[] = [
                ucfirst(str_replace('_', ' ', $type)),
                $sums['count'],
                ($sums['units'] > 0 ? '+' : '') . $sums['units'],
            ];
        }

        $net = (int) $movements->sum('quantity');
        $rows[] = ['<options=bold>Net</>', '', ($net > 0 ? '+' : '') . $net];

        $this->table(['What happened', 'Times', 'Units'], $rows);

        $this->verdict($batch, $movements);
    }

    /**
     * Does the figure on the shelf agree with the log?
     *
     * Anchored on the money trail rather than on an assumed opening balance.
     * The earliest audited change to this batch records what it held before
     * that change; every movement from then on should add up to what it holds
     * now.
     */
    private function verdict(Batch $batch, $movements): void
    {
        $anchor = AuditLog::where('auditable_type', Batch::class)
            ->where('auditable_id', (string) $batch->id)
            ->where('field', 'quantity')
            ->orderBy('id')
            ->first();

        if (! $anchor) {
            $this->line('    <fg=yellow>Cannot be checked.</> Nothing in the money trail for this batch, '
                . 'so there is no reliable figure to count forward from.');

            return;
        }

        $from = (int) $anchor->old_value;

        $applied = $movements
            ->where('created_at', '>=', $anchor->created_at)
            ->sum('quantity');

        $expected = $from + (int) $applied;
        $actual   = (int) $batch->quantity;

        $this->line("    Held {$from} on " . $anchor->created_at->format('j M Y, g:ia')
            . ', and ' . ($applied >= 0 ? '+' : '') . $applied . ' has been recorded since.');

        if ($expected === $actual) {
            $this->line("    <fg=green>Agrees.</> {$expected} expected, {$expected} on the shelf.");

            return;
        }

        $gap = $actual - $expected;

        $this->line("    <fg=red>Does not agree.</> {$expected} expected, {$actual} on the shelf — "
            . ($gap > 0 ? 'a surplus of ' . $gap : 'short by ' . abs($gap)) . '.');

        $this->line('    <fg=yellow>Before correcting it</>, check whether this batch was edited directly: '
            . 'a correction to a batch is not a movement, so a change made that way is a real reason '
            . 'for the two to differ. The Money Trail shows every change to this batch.');

        $this->line('    To correct it, use Inventory → Adjustments and set the true count, '
            . 'with a reason. That is recorded as a movement and will keep the two in step from then on.');
    }
}
