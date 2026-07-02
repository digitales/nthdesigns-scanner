<?php

namespace App\Services\Outreach;

use App\Models\OutreachSelection;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class OutreachQueueLoader
{
    private const STALE_DAYS = 30;

    public function __construct(
        private OutreachChannelResolver $channelResolver,
    ) {}

    public function selections(User $user, bool $bookedOnly, bool $withReport = false): EloquentCollection
    {
        return $this->baseQuery($user, $bookedOnly, $withReport)
            ->orderBy('created_at')
            ->limit((int) config('scanner.outreach_queue_max', 200))
            ->get();
    }

    /**
     * @param  array{
     *     booked?: bool,
     *     niche?: string|null,
     *     city?: string|null,
     *     min_score?: int|string|null,
     *     outreach_status?: string|null,
     *     sort?: string|null,
     * }  $filters
     */
    public function pipelinePaginator(User $user, array $filters): LengthAwarePaginator
    {
        $bookedOnly = (bool) ($filters['booked'] ?? false);
        $query = $this->baseQuery($user, $bookedOnly, withReport: true);

        $query->join('prospects', 'prospects.id', '=', 'outreach_selections.prospect_id')
            ->leftJoin('prospect_reports', 'prospect_reports.prospect_id', '=', 'prospects.id')
            ->select('outreach_selections.*');

        if (! empty($filters['niche'])) {
            $query->whereHas('prospect.search', fn (Builder $q) => $q
                ->where('niche', 'like', '%'.$filters['niche'].'%'));
        }

        if (! empty($filters['city'])) {
            $query->whereHas('prospect.search', fn (Builder $q) => $q
                ->where('city', 'like', '%'.$filters['city'].'%'));
        }

        if (isset($filters['min_score']) && $filters['min_score'] !== '' && $filters['min_score'] !== null) {
            $query->where('prospects.combined_score', '>=', (int) $filters['min_score']);
        }

        $this->applyOutreachStatusFilter($query, $user, $filters['outreach_status'] ?? null);

        match ($filters['sort'] ?? 'report_age') {
            'score' => $query->orderByDesc('prospects.combined_score'),
            'name' => $query->orderBy('prospects.business_name'),
            default => $query->orderBy('prospect_reports.updated_at'),
        };

        return $query
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * @param  Collection<int, int>  $prospectIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function latestEmailsByProspect(User $user, Collection $prospectIds): array
    {
        if ($prospectIds->isEmpty()) {
            return [];
        }

        $latestIds = \App\Models\OutreachEmail::query()
            ->selectRaw('MAX(id) as id')
            ->where('user_id', $user->id)
            ->whereIn('prospect_id', $prospectIds)
            ->groupBy('prospect_id', 'channel')
            ->pluck('id');

        if ($latestIds->isEmpty()) {
            return [];
        }

        return \App\Models\OutreachEmail::query()
            ->with(['prospect', 'fromMailbox'])
            ->whereIn('id', $latestIds)
            ->get()
            ->groupBy('prospect_id')
            ->map(function ($group) {
                $prospect = $group->first()->prospect;
                $visibleChannels = $prospect
                    ? $this->channelResolver->channelsFor($prospect)
                    : [];

                return $group
                    ->filter(function (\App\Models\OutreachEmail $email) use ($visibleChannels) {
                        $channel = $email->channel instanceof \App\Enums\OutreachChannel
                            ? $email->channel
                            : \App\Enums\OutreachChannel::tryFrom($email->getAttributes()['channel'] ?? 'email') ?? \App\Enums\OutreachChannel::Email;

                        return in_array($channel, $visibleChannels, true);
                    })
                    ->map(fn (\App\Models\OutreachEmail $email) => \App\Http\Resources\OutreachEmailResource::format($email))
                    ->values()
                    ->all();
            })
            ->all();
    }

    /**
     * @param  Collection<int, \App\Models\OutreachEmail>  $userOutreachEmails
     */
    public function outreachStatus(Collection $userOutreachEmails): string
    {
        if ($userOutreachEmails->contains(fn ($email) => $email->sent_at !== null)) {
            return 'sent';
        }

        if ($userOutreachEmails->isNotEmpty()) {
            return 'drafted';
        }

        return 'none';
    }

    public function refreshEligible(?ProspectReport $report, string $outreachStatus): bool
    {
        return $report !== null && $outreachStatus !== 'sent';
    }

    public function reportGeneratedAt(?ProspectReport $report): ?Carbon
    {
        if (! $report) {
            return null;
        }

        $generatedAt = $report->report_data['generated_at'] ?? null;

        if (is_string($generatedAt) && $generatedAt !== '') {
            return Carbon::parse($generatedAt);
        }

        return $report->created_at;
    }

    public function reportIsStale(?Carbon $generatedAt): bool
    {
        if (! $generatedAt) {
            return false;
        }

        return $generatedAt->lt(now()->subDays(self::STALE_DAYS));
    }

    public function outreachStatusLabel(string $status): string
    {
        return match ($status) {
            'sent' => 'Sent',
            'drafted' => 'Drafted',
            default => 'No draft',
        };
    }

    private function baseQuery(User $user, bool $bookedOnly, bool $withReport): HasMany
    {
        $relations = [
            'prospect.search',
            'prospect.report.booking',
            'prospect.outreachEmails' => fn ($q) => $q->where('user_id', $user->id),
        ];

        if ($withReport) {
            $relations[] = 'prospect.report';
        }

        $query = $user->outreachSelections()->with($relations);

        if ($bookedOnly) {
            $query->whereHas('prospect.report.booking');
        }

        return $query;
    }

    /**
     * @param  Builder|HasMany  $query
     */
    private function applyOutreachStatusFilter(Builder|HasMany $query, User $user, ?string $status): void
    {
        if ($status === null || $status === '') {
            return;
        }

        match ($status) {
            'sent' => $query->whereHas(
                'prospect.outreachEmails',
                fn (Builder $q) => $q->where('user_id', $user->id)->whereNotNull('sent_at'),
            ),
            'drafted' => $query
                ->whereHas(
                    'prospect.outreachEmails',
                    fn (Builder $q) => $q->where('user_id', $user->id)->whereNull('sent_at'),
                )
                ->whereDoesntHave(
                    'prospect.outreachEmails',
                    fn (Builder $q) => $q->where('user_id', $user->id)->whereNotNull('sent_at'),
                ),
            'none' => $query->whereDoesntHave(
                'prospect.outreachEmails',
                fn (Builder $q) => $q->where('user_id', $user->id),
            ),
            default => null,
        };
    }
}
