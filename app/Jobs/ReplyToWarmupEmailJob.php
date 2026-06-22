<?php

namespace App\Jobs;

use App\Models\WarmupMailbox;
use App\Models\WarmupSend;
use App\Services\WarmupSendService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReplyToWarmupEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public readonly int $sendId,
        public readonly int $fromMailboxId,
    ) {
        $this->onQueue('warmup');
    }

    public function handle(WarmupSendService $sendService): void
    {
        $send = WarmupSend::findOrFail($this->sendId);
        $from = WarmupMailbox::findOrFail($this->fromMailboxId);

        $sendService->replyToWarmupEmail($send, $from);
    }

    public function failed(Throwable $e): void
    {
        Log::warning('Warmup reply failed.', [
            'send_id' => $this->sendId,
            'from_mailbox_id' => $this->fromMailboxId,
            'error' => $e->getMessage(),
        ]);
    }
}
