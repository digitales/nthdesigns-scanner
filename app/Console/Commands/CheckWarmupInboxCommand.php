<?php

namespace App\Console\Commands;

use App\Jobs\ProcessWarmupInboxJob;
use App\Jobs\RetryStaleWarmupInboxJob;
use App\Models\WarmupMailbox;
use App\Models\WarmupSend;
use Illuminate\Console\Command;

class CheckWarmupInboxCommand extends Command
{
    protected $signature = 'warmup:check-inbox
                            {seed? : Seed mailbox ID to scan}
                            {--stale : Re-check all seed/outbox pairs with undelivered sends}
                            {--sync : Run synchronously instead of dispatching to the warmup queue}';

    protected $description = 'Scan seed inboxes for undelivered warmup emails';

    public function handle(): int
    {
        if ($this->option('stale')) {
            return $this->retryStale();
        }

        $seedId = $this->argument('seed');

        if ($seedId === null) {
            $this->error('Provide a seed mailbox ID or pass --stale.');

            return self::FAILURE;
        }

        $seed = WarmupMailbox::query()
            ->whereKey($seedId)
            ->where('is_seed_mailbox', true)
            ->first();

        if (! $seed) {
            $this->error("Seed mailbox {$seedId} not found.");

            return self::FAILURE;
        }

        $pairs = WarmupSend::query()
            ->where('to_mailbox_id', $seed->id)
            ->where('status', 'sent')
            ->select('from_mailbox_id', 'to_mailbox_id')
            ->distinct()
            ->get();

        if ($pairs->isEmpty()) {
            $this->warn("No undelivered sends for seed {$seed->email}.");

            return self::SUCCESS;
        }

        foreach ($pairs as $pair) {
            $this->dispatchInboxCheck($pair->from_mailbox_id, $pair->to_mailbox_id);
        }

        $this->info("Queued inbox check for {$pairs->count()} outbox/seed pair(s) on {$seed->email}.");

        return self::SUCCESS;
    }

    private function retryStale(): int
    {
        if ($this->option('sync')) {
            (new RetryStaleWarmupInboxJob)->handle();
            $this->info('Stale warmup inbox checks processed synchronously.');

            return self::SUCCESS;
        }

        RetryStaleWarmupInboxJob::dispatch();
        $this->info('RetryStaleWarmupInboxJob dispatched to the warmup queue.');

        return self::SUCCESS;
    }

    private function dispatchInboxCheck(int $outboxId, int $seedId): void
    {
        if ($this->option('sync')) {
            (new ProcessWarmupInboxJob($outboxId, $seedId))->handle(app(\App\Services\WarmupSendService::class));

            return;
        }

        ProcessWarmupInboxJob::dispatch($outboxId, $seedId);
    }
}
