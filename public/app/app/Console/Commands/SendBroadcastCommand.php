<?php

namespace App\Console\Commands;

use App\Models\Broadcast;
use App\Services\BroadcastSender;
use Illuminate\Console\Command;

class SendBroadcastCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'broadcast:send 
                            {id : The ID of the broadcast to send}
                            {--batch=5 : Number of recipients per micro-batch}
                            {--min-delay=3 : Minimum delay in seconds between messages}
                            {--max-delay=6 : Maximum delay in seconds between messages}
                            {--cooldown=5 : Cooldown seconds between batches}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely sends a prepared WhatsApp broadcast with anti-ban pacing and jitter';

    public function handle(BroadcastSender $sender): int
    {
        $id = (int) $this->argument('id');
        $broadcast = Broadcast::find($id);

        if (! $broadcast) {
            $this->error("Broadcast #{$id} not found.");
            return self::FAILURE;
        }

        $pending = $broadcast->pendingCount();
        if ($pending === 0) {
            $this->info("Broadcast #{$id} is already completed.");
            return self::SUCCESS;
        }

        $batchSize = max(1, (int) $this->option('batch'));
        $minDelay = max(0, (int) $this->option('min-delay'));
        $maxDelay = max($minDelay, (int) $this->option('max-delay'));
        $cooldown = max(0, (int) $this->option('cooldown'));

        $sender->setPacing($minDelay, $maxDelay);

        $this->info("Starting broadcast #{$id} to {$pending} pending recipients...");
        $this->info("Pacing: {$minDelay}-{$maxDelay}s between messages, {$cooldown}s cooldown between batches of {$batchSize}.");

        $totalSent = 0;
        $bar = $this->output->createProgressBar($pending);
        $bar->start();

        while (($remaining = $broadcast->pendingCount()) > 0) {
            $result = $sender->sendBatch($broadcast, $batchSize);
            $totalSent += $result['sent'];
            $bar->advance($result['sent']);

            if ($result['remaining'] > 0 && $cooldown > 0) {
                sleep($cooldown);
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("Broadcast #{$id} finished! Sent {$totalSent} messages.");

        return self::SUCCESS;
    }
}
