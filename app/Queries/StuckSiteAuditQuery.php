<?php

namespace App\Queries;

use App\Models\AuditJob;
use App\Models\Prospect;
use App\Support\AuditingQueuePresence;
use App\Support\RepairAuditScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class StuckSiteAuditQuery
{
    public static function query(int $stuckAfterMinutes): Builder
    {
        if (RepairAuditScope::siteAuditsDisabledByDriver()) {
            return Prospect::query()->whereRaw('0 = 1');
        }

        $cutoff = now()->subMinutes($stuckAfterMinutes);

        return RepairAuditScope::applySiteAuditProspectScope(Prospect::query())
            ->where('audit_status', 'pending')
            ->where(function (Builder $q) use ($cutoff) {
                $q->where('updated_at', '<', $cutoff)
                    ->orWhereHas('auditJobs', function (Builder $job) use ($cutoff) {
                        $job->where('job_type', 'accessibility')
                            ->where('status', 'running')
                            ->where('started_at', '<', $cutoff);
                    });
            })
            ->orderBy('id');
    }

    public static function ids(int $stuckAfterMinutes): array
    {
        return self::filterByQueuePresence(self::query($stuckAfterMinutes)->get(), $stuckAfterMinutes)
            ->pluck('id')
            ->all();
    }

    public static function get(
        ?int $searchId,
        ?int $prospectId,
        ?int $limit,
        int $stuckAfterMinutes,
    ): Collection {
        $query = RepairAuditScope::applySearchProspectFilters(
            self::query($stuckAfterMinutes),
            $searchId,
            $prospectId,
        );

        if ($limit !== null) {
            $query->limit($limit);
        }

        return self::filterByQueuePresence($query->get(), $stuckAfterMinutes);
    }

    public static function reasonFor(Prospect $prospect, int $stuckAfterMinutes): string
    {
        $runningJob = self::latestRunningAccessibilityJob($prospect->id);

        if ($runningJob && $runningJob->started_at?->lt(now()->subMinutes($stuckAfterMinutes))) {
            return "running audit_job #{$runningJob->id} stale without queue job";
        }

        $ageMinutes = $prospect->updated_at
            ? (int) $prospect->updated_at->diffInMinutes(now())
            : $stuckAfterMinutes;

        return "pending without queue job (stale {$ageMinutes}m)";
    }

    /**
     * @param  Collection<int, Prospect>  $prospects
     * @return Collection<int, Prospect>
     */
    private static function filterByQueuePresence(Collection $prospects, int $stuckAfterMinutes): Collection
    {
        if ($prospects->isEmpty()) {
            return $prospects;
        }

        $cutoff = now()->subMinutes($stuckAfterMinutes);
        $staleRunningProspectIds = self::staleRunningAccessibilityProspectIds(
            $prospects->pluck('id')->all(),
            $cutoff,
        );

        return $prospects->filter(function (Prospect $prospect) use ($cutoff, $staleRunningProspectIds) {
            if (AuditingQueuePresence::hasPendingAuditSiteJob($prospect->id)) {
                return false;
            }

            if ($prospect->updated_at?->gte($cutoff)) {
                if (! in_array($prospect->id, $staleRunningProspectIds, true)) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    private static function latestRunningAccessibilityJob(int $prospectId): ?AuditJob
    {
        return AuditJob::query()
            ->where('prospect_id', $prospectId)
            ->where('job_type', 'accessibility')
            ->where('status', 'running')
            ->latest('id')
            ->first();
    }

    /**
     * @param  list<int>  $prospectIds
     * @return list<int>
     */
    private static function staleRunningAccessibilityProspectIds(array $prospectIds, Carbon $cutoff): array
    {
        if ($prospectIds === []) {
            return [];
        }

        return AuditJob::query()
            ->whereIn('prospect_id', $prospectIds)
            ->where('job_type', 'accessibility')
            ->where('status', 'running')
            ->where('started_at', '<', $cutoff)
            ->pluck('prospect_id')
            ->unique()
            ->values()
            ->all();
    }
}
