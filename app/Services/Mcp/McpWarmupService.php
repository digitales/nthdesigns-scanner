<?php

namespace App\Services\Mcp;

use App\Models\User;
use App\Models\WarmupAlert;
use App\Models\WarmupMailbox;
use App\Models\WarmupSend;
use App\Services\WarmupSendService;

class McpWarmupService
{
    public function __construct(
        private WarmupSendService $sendService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function listWarmupMailboxes(User $user, ?string $status = null): array
    {
        $query = $user->warmupMailboxes()
            ->withCount([
                'sendsFrom as sends_today' => fn ($q) => $q->whereDate('sent_at', today()),
                'alerts as unread_alerts_count' => fn ($q) => $q->whereNull('read_at'),
            ]);

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        $mailboxes = $query->get();

        $outreachCount = $user->warmupMailboxes()->where('is_outreach_mailbox', true)->count();
        $seedCount = $user->warmupMailboxes()->where('is_seed_mailbox', true)->count();
        $limits = $user->warmupTierLimits();

        return [
            'plan' => [
                'tier' => $user->warmupTier(),
                'limits' => [
                    'max_outreach_mailboxes' => $limits['max_outreach_mailboxes'],
                    'max_seed_mailboxes' => $limits['max_seed_mailboxes'],
                    'pool_participation_allowed' => $limits['pool_participation_allowed'],
                ],
                'usage' => [
                    'outreach_mailboxes' => $outreachCount,
                    'seed_mailboxes' => $seedCount,
                ],
                'setup_complete' => $outreachCount >= 1 && $seedCount >= 1,
            ],
            'mailboxes' => $mailboxes->map(fn (WarmupMailbox $mailbox) => [
                'id' => $mailbox->id,
                'email' => $mailbox->email,
                'provider' => $mailbox->provider,
                'is_outreach_mailbox' => $mailbox->is_outreach_mailbox,
                'is_seed_mailbox' => $mailbox->is_seed_mailbox,
                'status' => $mailbox->status,
                'deliverability_score' => $mailbox->deliverability_score,
                'days_warming' => $mailbox->days_warming,
                'sends_today' => $mailbox->sends_today,
                'warmup_enabled' => $mailbox->warmup_enabled,
                'has_unread_alerts' => $mailbox->unread_alerts_count > 0,
            ])->values()->all(),
            'app_url' => route('warmup.index'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getWarmupMailbox(User $user, int $mailboxId): array
    {
        if ($mailboxId < 1) {
            throw new \InvalidArgumentException('mailbox_id is required.');
        }

        $mailbox = $this->findAuthorizedMailbox($user, $mailboxId);
        $weekStart = now()->startOfWeek();

        $recentSends = WarmupSend::query()
            ->where('from_mailbox_id', $mailbox->id)
            ->orderByDesc('sent_at')
            ->limit(50)
            ->get(['id', 'subject', 'sent_at', 'status', 'opened_at', 'replied_at', 'rescued_from_spam_at']);

        $alerts = WarmupAlert::query()
            ->where('warmup_mailbox_id', $mailbox->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'type', 'message', 'created_at', 'read_at']);

        $estimatedReady = $this->sendService->getEstimatedReadyDate($mailbox);

        return [
            'mailbox' => [
                'id' => $mailbox->id,
                'email' => $mailbox->email,
                'provider' => $mailbox->provider,
                'status' => $mailbox->status,
                'deliverability_score' => $mailbox->deliverability_score,
                'days_warming' => $mailbox->days_warming,
                'warmup_enabled' => $mailbox->warmup_enabled,
                'warmup_ramp_days' => $mailbox->warmup_ramp_days,
                'last_imap_check_at' => $mailbox->last_imap_check_at?->toIso8601String(),
                'estimated_ready_date' => $estimatedReady?->toDateString(),
            ],
            'stats' => [
                'sends_this_week' => WarmupSend::query()
                    ->where('from_mailbox_id', $mailbox->id)
                    ->where('sent_at', '>=', $weekStart)
                    ->count(),
                'replies_received' => WarmupSend::query()
                    ->where('from_mailbox_id', $mailbox->id)
                    ->whereNotNull('replied_at')
                    ->where('sent_at', '>=', $weekStart)
                    ->count(),
                'spam_rescues' => WarmupSend::query()
                    ->where('from_mailbox_id', $mailbox->id)
                    ->whereNotNull('rescued_from_spam_at')
                    ->where('sent_at', '>=', $weekStart)
                    ->count(),
            ],
            'recent_sends' => $recentSends->map(fn (WarmupSend $send) => [
                'id' => $send->id,
                'subject' => $send->subject,
                'sent_at' => $send->sent_at?->toIso8601String(),
                'status' => $send->status,
                'opened_at' => $send->opened_at?->toIso8601String(),
                'replied_at' => $send->replied_at?->toIso8601String(),
                'rescued_from_spam_at' => $send->rescued_from_spam_at?->toIso8601String(),
            ])->values()->all(),
            'alerts' => $alerts->map(fn (WarmupAlert $alert) => [
                'id' => $alert->id,
                'type' => $alert->type,
                'message' => $alert->message,
                'created_at' => $alert->created_at?->toIso8601String(),
                'read_at' => $alert->read_at?->toIso8601String(),
            ])->values()->all(),
            'app_url' => route('warmup.show', $mailbox),
        ];
    }

    private function findAuthorizedMailbox(User $user, int $mailboxId): WarmupMailbox
    {
        $mailbox = WarmupMailbox::query()->find($mailboxId);

        if ($mailbox === null || $mailbox->user_id !== $user->id) {
            throw new \InvalidArgumentException('Mailbox not found.');
        }

        return $mailbox;
    }
}
