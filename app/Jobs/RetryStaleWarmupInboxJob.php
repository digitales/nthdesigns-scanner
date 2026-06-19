<?php

namespace App\Jobs;

use App\Models\WarmupSend;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryStaleWarmupInboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('warmup');
    }

    public function handle(): void
    {
        $pairs = WarmupSend::query()
            ->where('status', 'sent')
            ->where('sent_at', '<=', now()->subMinutes(SendWarmupEmailJob::INBOX_CHECK_DELAY_MINUTES + 60))
            ->select('from_mailbox_id', 'to_mailbox_id')
            ->distinct()
            ->get();

        if ($pairs->isEmpty()) {
            return;
        }

        Log::info('Retrying stale warmup inbox checks.', [
            'pair_count' => $pairs->count(),
        ]);

        foreach ($pairs as $pair) {
            ProcessWarmupInboxJob::dispatch($pair->from_mailbox_id, $pair->to_mailbox_id);
        }
    }
}
