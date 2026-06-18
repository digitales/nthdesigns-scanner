<?php

namespace App\Jobs;

use App\Models\WarmupMailbox;
use App\Services\WarmupSendService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
    }
}
