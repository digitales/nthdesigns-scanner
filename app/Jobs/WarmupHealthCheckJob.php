<?php

namespace App\Jobs;

use App\Models\WarmupMailbox;
use App\Services\WarmupSendService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WarmupHealthCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('warmup');
    }

    public function handle(WarmupSendService $sendService): void
    {
        $mailboxes = WarmupMailbox::query()
            ->active()
            ->where('is_outreach_mailbox', true)
            ->get();

        foreach ($mailboxes as $mailbox) {
            $score = $sendService->calculateDeliverabilityScore($mailbox);

            $updates = ['deliverability_score' => $score];

            if ($score >= 80 && $mailbox->days_warming >= $mailbox->warmup_ramp_days) {
                $updates['status'] = 'ready';
            }

            $mailbox->update($updates);
        }
    }
}
