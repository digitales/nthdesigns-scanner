<?php

namespace App\Services;

use App\Enums\AuditJobStatus;
use App\Enums\AuditStatus;
use App\Models\Prospect;
use App\Models\Search;
use App\Support\ProspectSiteScan;

class SharedSearchSnapshotBuilder
{
    public function __construct(
        private ReportBuilderService $reportBuilder,
    ) {}

    /**
     * @return array{search: array<string, mixed>, prospects: list<array<string, mixed>>}
     */
    public function build(Search $search): array
    {
        $prospects = $search->prospects()
            ->with([
                'report',
                'auditJobs' => fn ($q) => $q->latest()->limit(1),
            ])
            ->orderByDesc('combined_score')
            ->get();

        return [
            'search' => $this->formatSearch($search, $prospects->count()),
            'prospects' => $prospects
                ->map(fn (Prospect $prospect) => $this->formatProspectRow($prospect))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSearch(Search $search, int $prospectCount): array
    {
        return [
            'niche' => $search->niche,
            'city' => $search->city,
            'country' => $search->country,
            'scan_type' => $search->scan_type->value,
            'source' => $search->source->value,
            'submitted_url' => $search->submitted_url,
            'total_found' => $search->total_found ?? $prospectCount,
            'prospect_count' => $prospectCount,
            'shared_at' => now()->toISOString(),
            'cpc_benchmark' => $search->cpc_benchmark !== null
                ? number_format((float) $search->cpc_benchmark, 2, '.', '')
                : null,
            'cpc_source' => $search->cpc_source,
            'cpc_keywords' => $search->cpc_keywords ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatProspectRow(Prospect $prospect): array
    {
        $cms = $this->reportBuilder->cmsForProspect($prospect);

        return [
            'business_name' => $prospect->business_name,
            'website_url' => $prospect->website_url,
            'combined_score' => $prospect->combined_score,
            'gbp_score' => $prospect->gbp_score,
            'a11y_score' => $prospect->a11y_score,
            'performance_score' => $prospect->performance_score,
            'dominant_angle' => $prospect->dominant_angle,
            'cms_badge' => $cms['badge'] ?? null,
            'cms_pending' => $cms['pending'] ?? false,
            'gbp_flags' => $prospect->gbp_flags ?? [],
            'a11y_flags' => $prospect->a11y_flags ?? [],
            'audit_status' => ($prospect->audit_status ?? AuditStatus::Pending)->value,
            'audit_error' => $this->auditError($prospect),
            'site_load_error' => $this->siteLoadError($prospect),
            'site_unreachable' => ProspectSiteScan::siteUnreachable($prospect),
            'report_url' => $prospect->report
                ? url('/r/'.$prospect->report->token)
                : null,
        ];
    }

    private function auditError(Prospect $prospect): ?string
    {
        if ($prospect->audit_status === AuditStatus::Failed) {
            $auditJob = $prospect->auditJobs->firstWhere('status', AuditJobStatus::Failed)
                ?? $prospect->auditJobs->first();

            return $auditJob?->error_message;
        }

        return $this->siteLoadError($prospect);
    }

    private function siteLoadError(Prospect $prospect): ?string
    {
        $payload = $prospect->raw_a11y_payload;

        if (! is_array($payload) || empty($payload['error'])) {
            return null;
        }

        return (string) $payload['error'];
    }
}
