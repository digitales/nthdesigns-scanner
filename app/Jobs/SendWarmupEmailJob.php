<?php

namespace App\Jobs;

use App\Models\WarmupAlert;
use App\Models\WarmupMailbox;
use App\Services\WarmupSendService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendWarmupEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $fromMailboxId,
        public readonly int $toMailboxId,
    ) {
        $this->onQueue('warmup');
    }

    public function handle(WarmupSendService $sendService): void
    {
        $from = WarmupMailbox::findOrFail($this->fromMailboxId);
        $to = WarmupMailbox::findOrFail($this->toMailboxId);

        $sendService->sendWarmupEmail($from, $to);

        $from->update(['consecutive_failures' => 0]);
    }

    public function failed(Throwable $e): void
    {
        $mailbox = WarmupMailbox::find($this->fromMailboxId);

        if (! $mailbox) {
            return;
        }

        $failures = $mailbox->consecutive_failures + 1;
        $updates = ['consecutive_failures' => $failures];

        if ($failures >= 3) {
            $updates['status'] = 'failed';
            $updates['warmup_enabled'] = false;

            WarmupAlert::create([
                'warmup_mailbox_id' => $mailbox->id,
                'type' => 'connection_failed',
                'message' => 'Warmup stopped after repeated connection failures. Check your mailbox credentials and try reconnecting.',
                'created_at' => now(),
            ]);
        }

        $mailbox->update($updates);
    }
}
