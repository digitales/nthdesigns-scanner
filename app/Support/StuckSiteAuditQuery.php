<?php

namespace App\Support;

use App\Models\AuditJob;
use App\Models\Prospect;
use Illuminate\Database\Eloquent\Builder;
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
        $runningJob = AuditJob::query()
            ->where('prospect_id', $prospect->id)
            ->where('job_type', 'accessibility')
            ->where('status', 'running')
            ->latest('id')
            ->first();

        if ($runningJob && $runningJob->started_at?->lt(now()->subMinutes($stuckAfterMinutes))) {
            return "running audit_job #{$runningJob->id} stale without queue job";
        }

        $ageMinutes = $prospect->updated_at
            ? (int) $prospect->updated_at->diffInMinutes(now())
            : $stuckAfterMinutes;

        return "pending without queue job (stale {$ageMinutes}m)";
    }

    private static function filterByQueuePresence(Collection $prospects, int $stuckAfterMinutes): Collection
    {
        return $prospects->filter(function (Prospect $prospect) use ($stuckAfterMinutes) {
            if (AuditingQueuePresence::hasPendingAuditSiteJob($prospect->id)) {
                return false;
            }

            $cutoff = now()->subMinutes($stuckAfterMinutes);

            if ($prospect->updated_at?->gte($cutoff)) {
                $hasStaleRunning = AuditJob::query()
                    ->where('prospect_id', $prospect->id)
                    ->where('job_type', 'accessibility')
                    ->where('status', 'running')
                    ->where('started_at', '<', $cutoff)
                    ->exists();

                if (! $hasStaleRunning) {
                    return false;
                }
            }

            return true;
        })->values();
    }
}
