<?php

namespace App\Jobs;

use App\Models\WarmupMailbox;
use App\Services\Warmup\WarmupNotifierService;
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

    public function handle(WarmupSendService $sendService, WarmupNotifierService $notifier): void
    {
        $mailboxes = WarmupMailbox::query()
            ->active()
            ->where('is_outreach_mailbox', true)
            ->get();

        foreach ($mailboxes as $mailbox) {
            $score = $sendService->calculateDeliverabilityScore($mailbox);
            $previousStatus = $mailbox->status;
            $newStatus = WarmupMailbox::statusForScore(
                $score,
                $mailbox->days_warming,
                $mailbox->warmup_ramp_days,
                $mailbox->status,
            );

            $mailbox->update([
                'deliverability_score' => $score,
                'status' => $newStatus,
            ]);

            if ($newStatus === 'ready' && $previousStatus !== 'ready') {
                $notifier->notify(
                    $mailbox,
                    'ready',
                    "{$mailbox->email} is ready for cold outreach — deliverability score is {$score}.",
                );
            }

            if ($newStatus === 'at_risk' && $previousStatus !== 'at_risk') {
                $notifier->notify(
                    $mailbox,
                    'at_risk',
                    'Deliverability score has dropped below 50. Review your DNS and sending patterns.',
                );
            }
        }
    }
}
