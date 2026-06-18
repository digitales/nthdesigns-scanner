<?php

namespace App\Jobs;

use App\Models\WarmupAlert;
use App\Models\WarmupMailbox;
use App\Models\WarmupSend;
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

    public function __construct(public readonly int $outboxId)
    {
        $this->onQueue('warmup');
    }

    public function handle(WarmupSendService $sendService): void
    {
        $seedIds = WarmupSend::query()
            ->where('from_mailbox_id', $this->outboxId)
            ->where('sent_at', '>=', now()->subHours(6))
            ->pluck('to_mailbox_id')
            ->unique();

        foreach ($seedIds as $seedId) {
            $seed = WarmupMailbox::find($seedId);
            if (! $seed) {
                continue;
            }

            $sendService->processInbox($seed);

            $unreplied = WarmupSend::query()
                ->where('from_mailbox_id', $this->outboxId)
                ->where('to_mailbox_id', $seedId)
                ->where('status', 'opened')
                ->whereNull('replied_at')
                ->get();

            foreach ($unreplied as $send) {
                ReplyToWarmupEmailJob::dispatch($send->id, $seedId)
                    ->delay(now()->addMinutes(rand(30, 240)));
            }
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

            WarmupAlert::create([
                'warmup_mailbox_id' => $mailbox->id,
                'type' => 'connection_failed',
                'message' => 'Warmup stopped after repeated inbox connection failures. Check your mailbox credentials and try reconnecting.',
                'created_at' => now(),
            ]);
        }

        $mailbox->update($updates);
    }
}
