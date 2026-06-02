<?php

namespace App\Support;

use App\Models\AuditJob;
use App\Models\ProspectReport;
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

    private static function filterEligible(Collection $reports, int $stuckAfterMinutes): Collection
    {
        $cutoff = now()->subMinutes($stuckAfterMinutes);

        return $reports->filter(function (ProspectReport $report) use ($cutoff, $stuckAfterMinutes) {
            $latest = self::latestScreenshotJob($report->prospect_id);

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
        return AuditJob::query()
            ->where('prospect_id', $prospectId)
            ->where('job_type', 'screenshot')
            ->latest('id')
            ->first();
    }
}
