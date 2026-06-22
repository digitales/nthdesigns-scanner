<?php

namespace App\Jobs;

use App\Models\WarmupSend;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryStaleWarmupRepliesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const REPLY_DELAY_HOURS = 5;

    public function __construct()
    {
        $this->onQueue('warmup');
    }

    public function handle(): void
    {
        $staleBefore = now()->subHours(self::REPLY_DELAY_HOURS);

        $sends = WarmupSend::query()
            ->whereIn('status', ['opened', 'rescued'])
            ->whereNull('replied_at')
            ->where(function ($query) use ($staleBefore) {
                $query->where(function ($query) use ($staleBefore) {
                    $query->whereNotNull('opened_at')
                        ->where('opened_at', '<=', $staleBefore);
                })->orWhere(function ($query) use ($staleBefore) {
                    $query->whereNotNull('rescued_from_spam_at')
                        ->where('rescued_from_spam_at', '<=', $staleBefore);
                });
            })
            ->get();

        if ($sends->isEmpty()) {
            return;
        }

        Log::info('Retrying stale warmup replies.', [
            'send_count' => $sends->count(),
        ]);

        foreach ($sends as $send) {
            ReplyToWarmupEmailJob::dispatch($send->id, $send->to_mailbox_id)
                ->delay(now()->addMinutes(rand(5, 30)));
        }
    }
}
