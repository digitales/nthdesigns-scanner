<?php

namespace App\Jobs;

use App\Models\WarmupMailbox;
use App\Models\WarmupSend;
use App\Services\Warmup\WarmupNotifierService;
use App\Services\WarmupSendService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessWarmupInboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public readonly int $outboxId,
        public readonly int $seedId,
    ) {
        $this->onQueue('warmup');
    }

    public function handle(WarmupSendService $sendService): void
    {
        $seed = WarmupMailbox::find($this->seedId);
        if (! $seed) {
            return;
        }

        $sendService->processInbox($seed);

        $unreplied = WarmupSend::query()
            ->where('from_mailbox_id', $this->outboxId)
            ->where('to_mailbox_id', $this->seedId)
            ->where('status', 'opened')
            ->whereNull('replied_at')
            ->get();

        foreach ($unreplied as $send) {
            ReplyToWarmupEmailJob::dispatch($send->id, $this->seedId)
                ->delay(now()->addMinutes(rand(30, 240)));
        }

        WarmupMailbox::whereKey($this->outboxId)->update(['consecutive_failures' => 0]);
    }

    public function failed(Throwable $e): void
    {
        $mailbox = WarmupMailbox::find($this->outboxId);

        if (! $mailbox) {
            return;
        }

        $failures = $mailbox->consecutive_failures + 1;
        $updates = ['consecutive_failures' => $failures];

        if ($failures >= 3) {
            $updates['status'] = 'failed';
            $updates['warmup_enabled'] = false;

            app(WarmupNotifierService::class)->notify(
                $mailbox,
                'connection_failed',
                'Warmup stopped after repeated inbox connection failures. Check your mailbox credentials and try reconnecting.',
            );
        }

        $mailbox->update($updates);
    }
}
