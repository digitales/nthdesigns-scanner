<?php

namespace App\Jobs;

use App\Models\WarmupMailbox;
use App\Services\WarmupMailboxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWarmupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('warmup');
    }

    public function handle(WarmupMailboxService $mailboxService): void
    {
        $outboxes = WarmupMailbox::query()
            ->active()
            ->where('is_outreach_mailbox', true)
            ->get();

        foreach ($outboxes as $outbox) {
            $volume = $mailboxService->getDailyVolume($outbox);
            $seeds = $mailboxService->getSeedPool($outbox);

            if ($seeds->isEmpty()) {
                continue;
            }

            $delayMinutes = 0;

            for ($i = 0; $i < $volume; $i++) {
                $seed = $seeds->random();

                $delayMinutes += rand(5, 30);

                SendWarmupEmailJob::dispatch($outbox->id, $seed->id)
                    ->delay(now()->addMinutes($delayMinutes));
            }

            ProcessWarmupInboxJob::dispatch($outbox->id)
                ->delay(now()->addMinutes($delayMinutes + 120));
        }
    }
}
