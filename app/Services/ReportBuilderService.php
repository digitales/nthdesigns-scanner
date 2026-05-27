<?php

namespace App\Services;

use App\Models\Prospect;
use App\Support\AxeViolationCopy;

class ReportBuilderService
{
    /**
     * Build structured report payload for storage and public display.
     *
     * @param  array<string, mixed>|null  $benchmarkPlace  Raw Places API place object
     * @return array<string, mixed>
     */
    public function build(Prospect $prospect, ?array $benchmarkPlace = null): array
    {
        $search = $prospect->search;
        $a11yPayload = $prospect->raw_a11y_payload ?? [];
        $lighthousePayload = $prospect->raw_lighthouse_payload ?? [];

        $benchmark = null;

        if ($benchmarkPlace) {
            $benchmark = (new BenchmarkNormalizer())->fromPlace($benchmarkPlace);
        }

        $comparison = [];

        if ($benchmark) {
            $comparison = [
                'review_gap'  => max(0, ($benchmark['review_count'] ?? 0) - $prospect->review_count),
                'photo_gap'   => max(0, ($benchmark['photo_count'] ?? 0) - $prospect->photo_count),
                'rating_gap'  => $benchmark['rating'] && $prospect->rating
                    ? round((float) $benchmark['rating'] - (float) $prospect->rating, 1)
                    : null,
            ];
        }

        $violationSummary = $this->summarizeViolations($a11yPayload);
        $topViolations = $this->extractTopViolations($a11yPayload, 5);
        $lighthouse = $this->extractLighthouse($lighthousePayload, $a11yPayload);
        $combined = (int) $prospect->combined_score;
        $grade = $this->combinedToGrade($combined);

        $search->loadMissing('user.setting');
        $bookingUrl = $search->user?->setting?->booking_url ?: config('scanner.report_booking_url');

        return [
            'niche'              => $search->niche,
            'city'               => $search->city,
            'country'            => $search->country,
            'scan_type'          => $search->scan_type,
            'booking_url'        => $bookingUrl,
            'generated_at'       => now()->toIso8601String(),
            'website_url'        => $prospect->website_url,
            'grade'              => $grade,
            'grade_label'        => $this->gradeLabel($grade),
            'performance_score'  => $prospect->performance_score,
            'violation_summary'  => $violationSummary,
            'top_violations'     => $topViolations,
            'lighthouse'         => $lighthouse,
            'prospect'           => [
                'business_name'   => $prospect->business_name,
                'address'         => $prospect->address,
                'phone'           => $prospect->phone,
                'website_url'     => $prospect->website_url,
                'rating'          => $prospect->rating,
                'review_count'    => $prospect->review_count,
                'photo_count'     => $prospect->photo_count,
                'has_description' => $prospect->has_description,
                'hours_complete'  => $prospect->hours_complete,
                'gbp_flags'       => $prospect->gbp_flags ?? [],
                'a11y_flags'      => $prospect->a11y_flags ?? [],
            ],
            'benchmark'  => $benchmark,
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

        $completedJob = $prospect->relationLoaded('auditJobs')
            ? $prospect->auditJobs
                ->where('job_type', 'accessibility')
                ->where('status', 'complete')
                ->sortByDesc('completed_at')
                ->first()
            : null;

        $auditedAt = $completedJob?->completed_at ?? $prospect->updated_at;

        return [
            'audited_at'        => $auditedAt?->toIso8601String() ?? now()->toIso8601String(),
            'url'               => $a11yPayload['url'] ?? $prospect->website_url ?? '',
            'summary'           => $this->summarizeViolations($a11yPayload),
            'pass_count'        => (int) ($a11yPayload['pass_count'] ?? 0),
            'incomplete_count'  => (int) ($a11yPayload['incomplete_count'] ?? 0),
            'top_violations'    => $this->extractTopViolations($a11yPayload, 5),
            'all_violations'    => $this->extractAllViolations($a11yPayload),
            'lighthouse'        => $lighthouse,
            'performance_score' => (int) $prospect->performance_score,
        ];
    }

    /**
     * Lighthouse scores for operator UI. Null when no metrics are stored.
     *
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
            default            => 'B+',
        };
    }

    public function healthToGrade(int $healthScore): string
    {
        return $this->combinedToGrade(max(0, min(100, 100 - $healthScore)));
    }

    public function gradeLabel(string $grade): string
    {
        return match ($grade) {
            'A'     => 'Strong online presence',
            'B'     => 'Good with room to improve',
            'C'     => 'Several gaps to address',
            'D'     => 'Needs attention',
            default => 'Significant issues found',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{critical: int, serious: int, moderate: int, minor: int, total: int}
     */
    public function summarizeViolations(array $payload): array
    {
        $counts = ['critical' => 0, 'serious' => 0, 'moderate' => 0, 'minor' => 0];

        foreach ($payload['violations'] ?? [] as $violation) {
            $impact = $violation['impact'] ?? 'minor';
            if (isset($counts[$impact])) {
                $counts[$impact]++;
            }
        }

        $counts['total'] = array_sum($counts);

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public function extractAllViolations(array $payload): array
    {
        return $this->mapViolations($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public function extractTopViolations(array $payload, int $limit = 5): array
    {
        return array_slice($this->mapViolations($payload), 0, $limit);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function mapViolations(array $payload): array
    {
        $violations = $payload['violations'] ?? [];
        $impactOrder = ['critical' => 0, 'serious' => 1, 'moderate' => 2, 'minor' => 3];

        usort($violations, function (array $a, array $b) use ($impactOrder) {
            $ia = $impactOrder[$a['impact'] ?? 'minor'] ?? 4;
            $ib = $impactOrder[$b['impact'] ?? 'minor'] ?? 4;

            return $ia <=> $ib;
        });

        $screenshotMap = collect($payload['violation_screenshots'] ?? [])
            ->keyBy('violation_id');

        return array_map(function (array $violation) use ($screenshotMap) {
            $tags = $violation['tags'] ?? [];
            $wcag = collect($tags)->first(fn ($tag) => preg_match('/^wcag\d+/', (string) $tag));
            $id = $violation['id'] ?? 'issue';
            $copy = AxeViolationCopy::forRule($id);

            return [
                'id'             => $id,
                'impact'         => $violation['impact'] ?? 'moderate',
                'description'    => $violation['description'] ?? $violation['help'] ?? 'Accessibility issue detected',
                'help'           => $violation['help'] ?? null,
                'wcag'           => $wcag ? strtoupper($wcag) : null,
                'nodes'          => count($violation['nodes'] ?? []),
                'screenshot_url' => $screenshotMap->get($id)['url'] ?? null,
                'user_impact'    => $copy['user_impact'],
                'fix_hint'       => $copy['fix_hint'],
            ];
        }, $violations);
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
            'performance'     => isset($lh['performance']) ? (int) $lh['performance'] : null,
            'accessibility'   => isset($lh['accessibility']) ? (int) $lh['accessibility'] : null,
            'seo'             => isset($lh['seo']) ? (int) $lh['seo'] : null,
            'best_practices'  => isset($lh['best_practices']) ? (int) $lh['best_practices'] : null,
        ];
    }
}
