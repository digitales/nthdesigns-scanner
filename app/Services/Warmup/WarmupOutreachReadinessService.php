<?php

namespace App\Services\Warmup;

use App\Models\User;
use App\Models\WarmupMailbox;
use App\Services\WarmupSendService;
use Illuminate\Support\Collection;

class WarmupOutreachReadinessService
{
    public function __construct(
        private WarmupSendService $sendService,
    ) {}

    /**
     * @return array{
     *     state: 'no_mailbox'|'not_ready'|'ready',
     *     primary_email: string|null,
     *     ready_mailboxes: list<array{id: int, email: string}>,
     *     estimated_ready_date: string|null,
     *     warming_email: string|null,
     *     status: string|null,
     * }
     */
    public function forUser(User $user): array
    {
        $outreachMailboxes = $user->warmupMailboxes()
            ->where('is_outreach_mailbox', true)
            ->orderBy('id')
            ->get();

        if ($outreachMailboxes->isEmpty()) {
            return [
                'state' => 'no_mailbox',
                'primary_email' => null,
                'ready_mailboxes' => [],
                'estimated_ready_date' => null,
                'warming_email' => null,
                'status' => null,
            ];
        }

        $readyMailboxes = $outreachMailboxes
            ->where('status', 'ready')
            ->values();

        if ($readyMailboxes->isNotEmpty()) {
            return [
                'state' => 'ready',
                'primary_email' => $readyMailboxes->first()->email,
                'ready_mailboxes' => $this->formatMailboxes($readyMailboxes),
                'estimated_ready_date' => null,
                'warming_email' => null,
                'status' => 'ready',
            ];
        }

        $primary = $outreachMailboxes->first();
        $estimatedReady = $this->sendService->getEstimatedReadyDate($primary);

        return [
            'state' => 'not_ready',
            'primary_email' => $primary->email,
            'ready_mailboxes' => [],
            'estimated_ready_date' => $estimatedReady?->toDateString(),
            'warming_email' => $primary->email,
            'status' => $primary->status,
        ];
    }

    /**
     * @param  Collection<int, WarmupMailbox>  $mailboxes
     * @return list<array{id: int, email: string}>
     */
    private function formatMailboxes(Collection $mailboxes): array
    {
        return $mailboxes
            ->map(fn (WarmupMailbox $mailbox) => [
                'id' => $mailbox->id,
                'email' => $mailbox->email,
            ])
            ->values()
            ->all();
    }
}
