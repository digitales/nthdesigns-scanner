<?php

namespace App\Services\Reports;

use App\Enums\AuditJobStatus;
use App\Enums\AuditJobType;
use App\Enums\AuditStatus;
use App\Models\Prospect;
use Illuminate\Support\Carbon;

final class OperatorAuditAssembler
{
    public function __construct(
        private ViolationMapper $violations,
        private LighthouseMetricsExtractor $lighthouse,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function build(Prospect $prospect): ?array
    {
        if ($prospect->audit_status !== AuditStatus::Complete) {
            return null;
        }

        $a11yPayload = $prospect->raw_a11y_payload;
        $lighthousePayload = $prospect->raw_lighthouse_payload ?? [];

        if ($a11yPayload === null || $a11yPayload === []) {
            return null;
        }

        $lighthouse = $this->lighthouseForProspect($prospect) ?? [
            'performance' => null,
            'accessibility' => null,
            'seo' => null,
            'best_practices' => null,
        ];
        $hasLighthouse = $this->lighthouse->hasMetrics($lighthouse);

        if (($a11yPayload['violations'] ?? []) === [] && ! $hasLighthouse && ! isset($a11yPayload['pass_count'])) {
            return null;
        }

        return [
            'audited_at' => $this->auditedAt($prospect)?->toIso8601String() ?? now()->toIso8601String(),
            'url' => $a11yPayload['url'] ?? $prospect->website_url ?? '',
            'summary' => $this->violations->summarize($a11yPayload),
            'pass_count' => (int) ($a11yPayload['pass_count'] ?? 0),
            'incomplete_count' => (int) ($a11yPayload['incomplete_count'] ?? 0),
            'top_violations' => $this->violations->top($a11yPayload, 5),
            'all_violations' => $this->violations->all($a11yPayload),
            'lighthouse' => $lighthouse,
            'performance_score' => (int) $prospect->performance_score,
        ];
    }

    /**
     * @return array{performance: int|null, accessibility: int|null, seo: int|null, best_practices: int|null}|null
     */
    public function lighthouseForProspect(Prospect $prospect): ?array
    {
        $a11yPayload = is_array($prospect->raw_a11y_payload) ? $prospect->raw_a11y_payload : [];
        $lighthouse = $this->lighthouse->extract($prospect->raw_lighthouse_payload ?? [], $a11yPayload);

        if ($lighthouse['performance'] === null && (int) $prospect->performance_score > 0) {
            $lighthouse['performance'] = (int) $prospect->performance_score;
        }

        return $this->lighthouse->hasMetrics($lighthouse) ? $lighthouse : null;
    }

    private function auditedAt(Prospect $prospect): ?Carbon
    {
        $completedJob = $prospect->relationLoaded('auditJobs')
            ? $prospect->auditJobs
                ->where('job_type', AuditJobType::Accessibility)
                ->where('status', AuditJobStatus::Complete)
                ->sortByDesc('completed_at')
                ->first()
            : null;

        return $completedJob?->completed_at ?? $prospect->updated_at;
    }
}
