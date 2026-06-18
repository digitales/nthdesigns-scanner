<?php

namespace App\Jobs;

use App\Exceptions\WarmupTransportException;
use App\Models\WarmupMailbox;
use App\Services\Warmup\WarmupNotifierService;
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

    public const INBOX_CHECK_DELAY_MINUTES = 120;

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

        try {
            $sendService->sendWarmupEmail($from, $to);
        } catch (WarmupTransportException $e) {
            if ($e->isRecipientRejected()) {
                $sendService->recordBouncedSend($from, $to);

                return;
            }

            throw $e;
        }

        $from->update(['consecutive_failures' => 0]);

        ProcessWarmupInboxJob::dispatch($from->id, $to->id)
            ->delay(now()->addMinutes(self::INBOX_CHECK_DELAY_MINUTES));
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

            app(WarmupNotifierService::class)->notify(
                $mailbox,
                'connection_failed',
                'Warmup stopped after repeated connection failures. Check your mailbox credentials and try reconnecting.',
            );
        }

        $mailbox->update($updates);
    }
}
