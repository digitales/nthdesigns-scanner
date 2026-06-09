<?php

namespace App\Services;

use App\Models\Prospect;
use App\Services\Reports\CmsLabelResolver;
use App\Services\Reports\LighthouseMetricsExtractor;
use App\Services\Reports\OperatorAuditAssembler;
use App\Services\Reports\OperatorPageSpeedBuilder;
use App\Services\Reports\ReportContextBuilder;
use App\Services\Reports\ReportGradeCalculator;
use App\Services\Reports\ViolationMapper;

class ReportBuilderService
{
    public function __construct(
        private CombineScoresService $combineScores,
        private BenchmarkNormalizer $benchmarks,
        private ViolationMapper $violations,
        private OperatorPageSpeedBuilder $pageSpeed,
        private CmsLabelResolver $cms,
        private ReportGradeCalculator $grades,
        private LighthouseMetricsExtractor $lighthouse,
        private OperatorAuditAssembler $operatorAudit,
        private ReportContextBuilder $reportContext,
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
        $lighthouse = $this->lighthouse->extract($lighthousePayload, $a11yPayload);
        $combined = (int) $prospect->combined_score;
        $grade = $this->grades->combinedToGrade($combined);

        $search->loadMissing('user.setting');
        $bookingUrl = $search->user?->setting?->booking_url ?: config('scanner.report_booking_url');

        $prospectSnapshot = [
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
        ];

        $reportContext = $this->reportContext->build([
            'scan_type' => $scanType,
            'city' => $search->city,
            'niche' => $search->niche,
            'gbp_score' => (int) $prospect->gbp_score,
            'a11y_score' => (int) $prospect->a11y_score,
            'performance_score' => (int) $prospect->performance_score,
            'violation_summary' => $violationSummary,
            'top_violations' => $topViolations,
            'comparison' => $comparison,
            'benchmark' => $benchmark,
            'prospect' => $prospectSnapshot,
            'lighthouse' => $lighthouse,
        ]);

        return [
            'niche' => $search->niche,
            'city' => $search->city,
            'country' => $search->country,
            'scan_type' => $scanType,
            'gbp_score' => (int) $prospect->gbp_score,
            'a11y_score' => (int) $prospect->a11y_score,
            'booking_url' => $bookingUrl,
            'generated_at' => now()->toIso8601String(),
            'website_url' => $prospect->website_url,
            'grade' => $grade,
            'grade_label' => $this->grades->gradeLabel($grade),
            'combined_score' => $combined,
            'performance_score' => $prospect->performance_score,
            'violation_summary' => $violationSummary,
            'top_violations' => $topViolations,
            'lighthouse' => $lighthouse,
            'report_context' => $reportContext,
            'prospect' => $prospectSnapshot,
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
        return $this->operatorAudit->build($prospect);
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
        return $this->operatorAudit->lighthouseForProspect($prospect);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buildOperatorPageSpeed(Prospect $prospect): ?array
    {
        return $this->pageSpeed->build($prospect);
    }

    public function combinedToGrade(int $combinedScore): string
    {
        return $this->grades->combinedToGrade($combinedScore);
    }

    public function healthToGrade(int $healthScore): string
    {
        return $this->grades->healthToGrade($healthScore);
    }

    public function gradeLabel(string $grade): string
    {
        return $this->grades->gradeLabel($grade);
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

        return $this->benchmarks->fromPlace($source);
    }

    /**
     * @param  array<string, mixed>  $lighthousePayload
     * @param  array<string, mixed>  $a11yPayload
     * @return array{performance: int|null, accessibility: int|null, seo: int|null, best_practices: int|null}
     */
    public function extractLighthouse(array $lighthousePayload, array $a11yPayload): array
    {
        return $this->lighthouse->extract($lighthousePayload, $a11yPayload);
    }
}
