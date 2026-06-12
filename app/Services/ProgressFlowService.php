<?php

namespace App\Services;

use App\Enums\AuditStatus;
use App\Enums\ScanType;
use App\Enums\SearchStatus;
use App\Models\Prospect;
use App\Models\Search;
use App\Support\ProspectSiteScan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ProgressFlowService
{
    /**
     * @param  Collection<int, Prospect>  $prospects
     * @return array<string, mixed>
     */
    public function searchFlow(Search $search, Collection $prospects): array
    {
        $phase = $this->phaseForSearch($search);
        $total = $search->total_found;
        $prospectsTotal = $prospects->count();
        $audited = $prospects->filter(
            fn (Prospect $prospect) => ($prospect->audit_status ?? AuditStatus::Pending) !== AuditStatus::Pending
        )->count();

        if ($phase === 'discovering') {
            $progress = $prospectsTotal;
        } else {
            $progress = $audited;
        }

        $normalizedTotal = is_int($total) && $total > 0 ? $total : null;
        $effectiveTotal = $normalizedTotal ?? ($prospectsTotal > 0 ? $prospectsTotal : null);
        $safeProgress = $effectiveTotal !== null ? min($progress, $effectiveTotal) : $progress;

        return [
            'phase' => $phase,
            'progress' => $safeProgress,
            'total' => $effectiveTotal,
            'percent' => $effectiveTotal !== null && $effectiveTotal > 0
                ? (int) round(($safeProgress / $effectiveTotal) * 100)
                : null,
            'duration_bucket' => $this->durationBucket($search->created_at),
            'message' => $this->searchMessage($phase, $safeProgress, $effectiveTotal),
            'search_complete' => in_array($search->status, [SearchStatus::Complete, SearchStatus::Failed], true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function prospectFlow(Prospect $prospect, Search $search): array
    {
        $step = $this->prospectStep($prospect);
        $startedAt = $this->stepStartedAt($prospect);

        return [
            'audit_status' => ($prospect->audit_status ?? AuditStatus::Pending)->value,
            'current_step' => $step,
            'step_started_at' => $startedAt?->toIso8601String(),
            'step_duration_bucket' => $this->durationBucket($startedAt ?? $prospect->updated_at ?? $prospect->created_at),
            'status_message' => $this->prospectMessage($step, $search, $prospect),
        ];
    }

    private function phaseForSearch(Search $search): string
    {
        return match ($search->status) {
            SearchStatus::Pending => 'queued',
            SearchStatus::Discovering => 'discovering',
            SearchStatus::Auditing => 'auditing',
            SearchStatus::Complete => 'complete',
            SearchStatus::Failed => 'failed',
            default => 'discovering',
        };
    }

    private function searchMessage(string $phase, int $progress, ?int $total): string
    {
        if ($phase === 'queued') {
            return 'Starting soon.';
        }

        if ($phase === 'discovering') {
            return $total === null
                ? "Discovered {$progress} prospects so far."
                : "Discovered {$progress} of {$total} prospects.";
        }

        if ($phase === 'auditing') {
            return $total === null
                ? "Audited {$progress} prospects so far."
                : "Audited {$progress} of {$total} prospects.";
        }

        if ($phase === 'complete') {
            return $total === null
                ? "Completed {$progress} prospects."
                : "Completed {$progress} of {$total} prospects.";
        }

        return 'Search failed.';
    }

    private function prospectStep(Prospect $prospect): string
    {
        $status = $prospect->audit_status ?? AuditStatus::Pending;
        $hasReport = $prospect->relationLoaded('report') ? $prospect->report !== null : false;

        if (in_array($status, [AuditStatus::Complete, AuditStatus::Skipped], true)) {
            return $hasReport ? 'done' : 'report';
        }

        if ($status === AuditStatus::Failed) {
            return 'a11y';
        }

        if (empty($prospect->raw_gbp_payload) && empty($prospect->gbp_score)) {
            return 'discovery';
        }

        if (! empty($prospect->raw_a11y_payload) && ! empty($prospect->raw_lighthouse_payload)) {
            return 'scoring';
        }

        if (! empty($prospect->raw_a11y_payload)) {
            return 'performance';
        }

        return 'a11y';
    }

    private function prospectMessage(string $step, Search $search, Prospect $prospect): string
    {
        if (($prospect->audit_status ?? AuditStatus::Pending) === AuditStatus::Failed
            && ProspectSiteScan::siteUnreachable($prospect)) {
            return 'Site unreachable';
        }

        return match ($step) {
            'discovery' => 'Discovering business profile',
            'gbp' => 'Scoring Google Business Profile',
            'a11y' => 'Running accessibility audit',
            'performance' => 'Running performance audit',
            'scoring' => 'Combining audit scores',
            'report' => $search->scan_type === ScanType::GbpOnly ? 'Finalizing results' : 'Generating report',
            'done' => 'Complete',
            default => 'In progress',
        };
    }

    private function stepStartedAt(Prospect $prospect): ?Carbon
    {
        if ($prospect->relationLoaded('auditJobs')) {
            $job = $prospect->auditJobs->first();
            if ($job?->started_at instanceof Carbon) {
                return $job->started_at;
            }
        }

        return null;
    }

    private function durationBucket(?Carbon $from): string
    {
        if ($from === null) {
            return '<30s';
        }

        $seconds = max(0, $from->diffInSeconds(now()));

        if ($seconds < 30) {
            return '<30s';
        }
        if ($seconds < 120) {
            return '30-120s';
        }
        if ($seconds < 300) {
            return '2-5m';
        }

        return '5m+';
    }
}
