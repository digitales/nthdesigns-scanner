<?php

namespace App\Services;

use App\Models\Prospect;
use App\Services\Reports\CmsLabelResolver;
use App\Services\Reports\OperatorPageSpeedBuilder;
use App\Services\Reports\ViolationMapper;

class ReportBuilderService
{
    public function __construct(
        private CombineScoresService $combineScores,
        private ViolationMapper $violations,
        private OperatorPageSpeedBuilder $pageSpeed,
        private CmsLabelResolver $cms,
    ) {}

    /**
     * Build structured report payload for storage and public display.
     *
     * @param  array<string, mixed>|null  $benchmarkSource  Raw Places API place or normalized benchmark snapshot
     * @return array<string, mixed>
     */
    public function build(Prospect $prospect, ?array $benchmarkSource = null): array
    {
        $search = $prospect->search;
        $scanType = $this->combineScores->effectiveScanType($prospect, $search->scan_type);
        $a11yPayload = $prospect->raw_a11y_payload ?? [];
        $lighthousePayload = $prospect->raw_lighthouse_payload ?? [];

        $benchmark = $this->normalizeBenchmark($benchmarkSource);

        $comparison = [];

        if ($benchmark) {
            $comparison = [
                'review_gap' => max(0, ($benchmark['review_count'] ?? 0) - $prospect->review_count),
                'photo_gap' => max(0, ($benchmark['photo_count'] ?? 0) - $prospect->photo_count),
                'rating_gap' => $benchmark['rating'] && $prospect->rating
                    ? round((float) $benchmark['rating'] - (float) $prospect->rating, 1)
                    : null,
            ];
        }

        $violationSummary = $this->violations->summarize($a11yPayload);
        $topViolations = $this->violations->top($a11yPayload, 5);
        $lighthouse = $this->extractLighthouse($lighthousePayload, $a11yPayload);
        $combined = (int) $prospect->combined_score;
        $grade = $this->combinedToGrade($combined);

        $search->loadMissing('user.setting');
        $bookingUrl = $search->user?->setting?->booking_url ?: config('scanner.report_booking_url');

        return [
            'niche' => $search->niche,
            'city' => $search->city,
            'country' => $search->country,
            'scan_type' => $scanType,
            'booking_url' => $bookingUrl,
            'generated_at' => now()->toIso8601String(),
            'website_url' => $prospect->website_url,
            'grade' => $grade,
            'grade_label' => $this->gradeLabel($grade),
            'performance_score' => $prospect->performance_score,
            'violation_summary' => $violationSummary,
            'top_violations' => $topViolations,
            'lighthouse' => $lighthouse,
            'prospect' => [
                'business_name' => $prospect->business_name,
                'address' => $prospect->address,
                'phone' => $prospect->phone,
                'website_url' => $prospect->website_url,
                'rating' => $prospect->rating,
                'review_count' => $prospect->review_count,
                'photo_count' => $prospect->photo_count,
                'has_description' => $prospect->has_description,
                'hours_complete' => $prospect->hours_complete,
                'gbp_flags' => $prospect->gbp_flags ?? [],
                'a11y_flags' => $prospect->a11y_flags ?? [],
            ],
            'benchmark' => $benchmark,
            'comparison' => $comparison,
        ];
    }

    /**
     * Shaped site audit for operator prospect detail. Null when section should be hidden.
     *
     * @return array<string, mixed>|null
     */
    public function buildOperatorAudit(Prospect $prospect): ?array
    {
        if ($prospect->audit_status !== 'complete') {
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
        $hasLighthouse = $this->hasLighthouseMetrics($lighthouse);

        if (($a11yPayload['violations'] ?? []) === [] && ! $hasLighthouse && ! isset($a11yPayload['pass_count'])) {
            return null;
        }

        $auditedAt = $this->auditedAt($prospect);

        return [
            'audited_at' => $auditedAt?->toIso8601String() ?? now()->toIso8601String(),
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
     * @return array<string, mixed>|null
     */
    public function cmsForProspect(Prospect $prospect): ?array
    {
        return $this->cms->forProspect($prospect);
    }

    /**
     * @return array{performance: int|null, accessibility: int|null, seo: int|null, best_practices: int|null}|null
     */
    public function lighthouseForProspect(Prospect $prospect): ?array
    {
        $a11yPayload = is_array($prospect->raw_a11y_payload) ? $prospect->raw_a11y_payload : [];
        $lighthouse = $this->extractLighthouse($prospect->raw_lighthouse_payload ?? [], $a11yPayload);

        if ($lighthouse['performance'] === null && (int) $prospect->performance_score > 0) {
            $lighthouse['performance'] = (int) $prospect->performance_score;
        }

        return $this->hasLighthouseMetrics($lighthouse) ? $lighthouse : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buildOperatorPageSpeed(Prospect $prospect): ?array
    {
        return $this->pageSpeed->build($prospect);
    }

    /**
     * @param  array{performance: int|null, accessibility: int|null, seo: int|null, best_practices: int|null}  $lighthouse
     */
    private function hasLighthouseMetrics(array $lighthouse): bool
    {
        return $lighthouse['performance'] !== null
            || $lighthouse['accessibility'] !== null
            || $lighthouse['seo'] !== null
            || $lighthouse['best_practices'] !== null;
    }

    /** @see resources/css/tokens.css — grade thresholds from combined score */
    public function combinedToGrade(int $combinedScore): string
    {
        return match (true) {
            $combinedScore >= 85 => 'D',
            $combinedScore >= 70 => 'C',
            $combinedScore >= 50 => 'C+',
            $combinedScore >= 30 => 'B',
            default => 'B+',
        };
    }

    public function healthToGrade(int $healthScore): string
    {
        return $this->combinedToGrade(max(0, min(100, 100 - $healthScore)));
    }

    public function gradeLabel(string $grade): string
    {
        return match ($grade) {
            'A' => 'Strong online presence',
            'B' => 'Good with room to improve',
            'C' => 'Several gaps to address',
            'D' => 'Needs attention',
            default => 'Significant issues found',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{critical: int, serious: int, moderate: int, minor: int, total: int}
     */
    public function summarizeViolations(array $payload): array
    {
        return $this->violations->summarize($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public function extractAllViolations(array $payload): array
    {
        return $this->violations->all($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public function extractTopViolations(array $payload, int $limit = 5): array
    {
        return $this->violations->top($payload, $limit);
    }

    /**
     * @param  array<string, mixed>|null  $source
     * @return array<string, mixed>|null
     */
    private function normalizeBenchmark(?array $source): ?array
    {
        if ($source === null) {
            return null;
        }

        if (array_key_exists('review_count', $source)) {
            return $source;
        }

        return (new BenchmarkNormalizer())->fromPlace($source);
    }

    /**
     * @param  array<string, mixed>  $lighthousePayload
     * @param  array<string, mixed>  $a11yPayload
     * @return array{performance: int|null, accessibility: int|null, seo: int|null, best_practices: int|null}
     */
    public function extractLighthouse(array $lighthousePayload, array $a11yPayload): array
    {
        $lh = $lighthousePayload['lighthouse'] ?? $lighthousePayload ?? $a11yPayload['lighthouse'] ?? [];

        return [
            'performance' => isset($lh['performance']) ? (int) $lh['performance'] : null,
            'accessibility' => isset($lh['accessibility']) ? (int) $lh['accessibility'] : null,
            'seo' => isset($lh['seo']) ? (int) $lh['seo'] : null,
            'best_practices' => isset($lh['best_practices']) ? (int) $lh['best_practices'] : null,
        ];
    }

    private function auditedAt(Prospect $prospect): ?\Illuminate\Support\Carbon
    {
        $completedJob = $prospect->relationLoaded('auditJobs')
            ? $prospect->auditJobs
                ->where('job_type', 'accessibility')
                ->where('status', 'complete')
                ->sortByDesc('completed_at')
                ->first()
            : null;

        return $completedJob?->completed_at ?? $prospect->updated_at;
    }
}
