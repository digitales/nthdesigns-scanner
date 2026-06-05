<?php

namespace App\Queries;

use App\Models\AuditJob;
use App\Models\ProspectReport;
use App\Support\AuditingQueuePresence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class FailedScreenshotQuery
{
    public static function query(int $stuckAfterMinutes): Builder
    {
        return ProspectReport::query()
            ->with(['prospect.search'])
            ->whereHas('prospect', fn (Builder $q) => $q
                ->whereNotNull('website_url')
                ->where('website_url', '!=', ''))
            ->whereHas('prospect.search', fn (Builder $q) => $q
                ->whereIn('status', ['auditing', 'complete']))
            ->orderBy('id');
    }

    public static function ids(int $stuckAfterMinutes): array
    {
        return self::filterEligible(self::query($stuckAfterMinutes)->get(), $stuckAfterMinutes)
            ->pluck('id')
            ->all();
    }

    public static function get(
        ?int $searchId,
        ?int $prospectId,
        ?int $limit,
        int $stuckAfterMinutes,
    ): Collection {
        $query = self::query($stuckAfterMinutes);

        if ($searchId !== null) {
            $query->whereHas('prospect', fn (Builder $q) => $q->where('search_id', $searchId));
        }

        if ($prospectId !== null) {
            $query->where('prospect_id', $prospectId);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        return self::filterEligible($query->get(), $stuckAfterMinutes);
    }

    public static function reasonFor(ProspectReport $report): string
    {
        $latest = self::latestScreenshotJob($report->prospect_id);

        if ($latest?->status === 'running') {
            return 'screenshot running stale without queue job';
        }

        return 'screenshot failed';
    }

    /**
     * @param  Collection<int, ProspectReport>  $reports
     * @return Collection<int, ProspectReport>
     */
    private static function filterEligible(Collection $reports, int $stuckAfterMinutes): Collection
    {
        if ($reports->isEmpty()) {
            return $reports;
        }

        $cutoff = now()->subMinutes($stuckAfterMinutes);
        $latestJobs = self::latestScreenshotJobsForProspects($reports->pluck('prospect_id')->unique()->all());

        return $reports->filter(function (ProspectReport $report) use ($cutoff, $stuckAfterMinutes, $latestJobs) {
            $latest = $latestJobs->get($report->prospect_id);

            if (! $latest || ! in_array($latest->status, ['failed', 'running'], true)) {
                return false;
            }

            if ($latest->status === 'failed') {
                return true;
            }

            if ($latest->started_at?->gte($cutoff)) {
                return false;
            }

            return ! AuditingQueuePresence::hasPendingScreenshotJob($report->id);
        })->values();
    }

    private static function latestScreenshotJob(int $prospectId): ?AuditJob
    {
        return self::latestScreenshotJobsForProspects([$prospectId])->get($prospectId);
    }

    /**
     * @param  list<int>  $prospectIds
     * @return Collection<int, AuditJob>
     */
    private static function latestScreenshotJobsForProspects(array $prospectIds): Collection
    {
        if ($prospectIds === []) {
            return collect();
        }

        $latestIds = AuditJob::query()
            ->selectRaw('MAX(id) as id')
            ->whereIn('prospect_id', $prospectIds)
            ->where('job_type', 'screenshot')
            ->groupBy('prospect_id')
            ->pluck('id');

        if ($latestIds->isEmpty()) {
            return collect();
        }

        return AuditJob::query()
            ->whereIn('id', $latestIds)
            ->get()
            ->keyBy('prospect_id');
    }
}
