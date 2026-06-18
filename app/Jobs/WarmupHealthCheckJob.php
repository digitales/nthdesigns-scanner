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

            if ($newStatus === 'at_risk' && $previousStatus !== 'at_risk') {
                $hasUnreadAlert = $mailbox->alerts()
                    ->where('type', 'at_risk')
                    ->whereNull('read_at')
                    ->exists();

                if (! $hasUnreadAlert) {
                    WarmupAlert::create([
                        'warmup_mailbox_id' => $mailbox->id,
                        'type' => 'at_risk',
                        'message' => 'Deliverability score has dropped below 50. Review your DNS and sending patterns.',
                        'created_at' => now(),
                    ]);
                }
            }
        }
    }
}
