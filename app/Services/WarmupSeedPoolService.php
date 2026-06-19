<?php

namespace App\Services;

use App\Models\User;
use App\Models\WarmupMailbox;
use App\Models\WarmupSend;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class WarmupSeedPoolService
{
    /**
     * @return array{own: Collection<int, WarmupMailbox>, pool: Collection<int, WarmupMailbox>}
     */
    public function seedGroupsForOutbox(WarmupMailbox $outbox): array
    {
        $recentlyUsed = $this->recentlyUsedSeedIds($outbox);

        $own = $this->seedsPreferringFresh(
            WarmupMailbox::query()
                ->where('user_id', $outbox->user_id)
                ->where('id', '!=', $outbox->id)
                ->where('is_seed_mailbox', true),
            $recentlyUsed,
        );

        $pool = collect();

        $user = $outbox->user ?? User::find($outbox->user_id);

        if ($user && $user->warmupTierLimits()['pool_participation_allowed']) {
            $outreachDomain = $this->emailDomain($outbox->email);

            $pool = $this->seedsPreferringFresh(
                WarmupMailbox::query()
                    ->where('is_seed_mailbox', true)
                    ->where('is_pool_participant', true)
                    ->where('status', '!=', 'failed')
                    ->where('user_id', '!=', $outbox->user_id),
                $recentlyUsed,
            )
                ->filter(fn (WarmupMailbox $seed) => $this->emailDomain($seed->email) !== $outreachDomain)
                ->values();
        }

        return [
            'own' => $own,
            'pool' => $pool,
        ];
    }

    /**
     * Prefer seeds not used in the last 24 hours, but fall back to all candidates
     * when every seed was recently used (common with small seed pools).
     *
     * @param  array<int, int>  $recentlyUsed
     * @return Collection<int, WarmupMailbox>
     */
    private function seedsPreferringFresh($query, array $recentlyUsed): Collection
    {
        $candidates = (clone $query)->get();

        if ($candidates->isEmpty() || $recentlyUsed === []) {
            return $candidates;
        }

        $fresh = $candidates->whereNotIn('id', $recentlyUsed)->values();

        return $fresh->isNotEmpty() ? $fresh : $candidates;
    }

    public function eligibleSeedsForOutbox(WarmupMailbox $outbox): Collection
    {
        $groups = $this->seedGroupsForOutbox($outbox);

        return $groups['own']->concat($groups['pool']);
    }

    public function countActivePoolSeeds(): int
    {
        return WarmupMailbox::query()
            ->where('is_seed_mailbox', true)
            ->where('is_pool_participant', true)
            ->where('status', '!=', 'failed')
            ->count();
    }

    public function poolReady(): bool
    {
        return $this->countActivePoolSeeds() >= config('warmup_pool.min_size');
    }

    public function canStartWarmup(User $user): bool
    {
        $hasOutreach = $user->warmupMailboxes()->where('is_outreach_mailbox', true)->exists();

        if (! $hasOutreach) {
            return false;
        }

        $ownSeedCount = $user->warmupMailboxes()->where('is_seed_mailbox', true)->count();

        if ($ownSeedCount >= 2) {
            return true;
        }

        $limits = $user->warmupTierLimits();

        if ($limits['pool_participation_allowed'] && $this->poolReady()) {
            return true;
        }

        return false;
    }

    /**
     * @return array<int, int>
     */
    private function recentlyUsedSeedIds(WarmupMailbox $outbox): array
    {
        return WarmupSend::query()
            ->where('from_mailbox_id', $outbox->id)
            ->where('sent_at', '>=', now()->subHours(24))
            ->pluck('to_mailbox_id')
            ->all();
    }

    private function emailDomain(string $email): string
    {
        return Str::lower(Str::after($email, '@'));
    }
}
