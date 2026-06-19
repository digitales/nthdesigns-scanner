<?php

namespace App\Console\Commands;

use App\Jobs\ProcessWarmupJob;
use App\Services\WarmupMailboxService;
use Illuminate\Console\Command;

class ProcessWarmupCommand extends Command
{
    protected $signature = 'warmup:process
                            {--outbox= : Limit to one outreach mailbox ID}
                            {--sync : Run synchronously instead of dispatching to the warmup queue}';

    protected $description = 'Schedule today\'s warmup sends for active outreach mailboxes';

    public function handle(WarmupMailboxService $mailboxService): int
    {
        $outboxId = $this->option('outbox') !== null ? (int) $this->option('outbox') : null;

        if ($this->option('sync')) {
            (new ProcessWarmupJob($outboxId))->handle($mailboxService);
            $this->info('Warmup processed synchronously.');

            return self::SUCCESS;
        }

        ProcessWarmupJob::dispatch($outboxId);
        $this->info('ProcessWarmupJob dispatched to the warmup queue.');

        return self::SUCCESS;
    }
}
