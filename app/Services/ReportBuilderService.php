<?php

namespace App\Services;

use App\Models\Prospect;

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
            $benchmark = [
                'place_id'     => $benchmarkPlace['id'] ?? null,
                'name'         => $benchmarkPlace['displayName']['text'] ?? 'Top local competitor',
                'rating'       => $benchmarkPlace['rating'] ?? null,
                'review_count' => $benchmarkPlace['userRatingCount'] ?? 0,
                'photo_count'  => count($benchmarkPlace['photos'] ?? []),
            ];
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
    public function extractTopViolations(array $payload, int $limit = 5): array
    {
        $violations = $payload['violations'] ?? [];
        $impactOrder = ['critical' => 0, 'serious' => 1, 'moderate' => 2, 'minor' => 3];

        usort($violations, function (array $a, array $b) use ($impactOrder) {
            $ia = $impactOrder[$a['impact'] ?? 'minor'] ?? 4;
            $ib = $impactOrder[$b['impact'] ?? 'minor'] ?? 4;

            return $ia <=> $ib;
        });

        $top = array_slice($violations, 0, $limit);

        $screenshotMap = collect($payload['violation_screenshots'] ?? [])
            ->keyBy('violation_id');

        return array_map(function (array $violation) use ($screenshotMap) {
            $tags = $violation['tags'] ?? [];
            $wcag = collect($tags)->first(fn ($tag) => preg_match('/^wcag\d+/', (string) $tag));
            $id = $violation['id'] ?? 'issue';

            return [
                'id'             => $id,
                'impact'         => $violation['impact'] ?? 'moderate',
                'description'    => $violation['description'] ?? $violation['help'] ?? 'Accessibility issue detected',
                'help'           => $violation['help'] ?? null,
                'wcag'           => $wcag ? strtoupper($wcag) : null,
                'nodes'          => count($violation['nodes'] ?? []),
                'screenshot_url' => $screenshotMap->get($id)['url'] ?? null,
            ];
        }, $top);
    }

    /**
     * @param  array<string, mixed>  $lighthousePayload
     * @param  array<string, mixed>  $a11yPayload
     * @return array{performance: int|null, accessibility: int|null, seo: int|null}
     */
    public function extractLighthouse(array $lighthousePayload, array $a11yPayload): array
    {
        $lh = $lighthousePayload['lighthouse'] ?? $lighthousePayload ?? $a11yPayload['lighthouse'] ?? [];

        return [
            'performance'    => isset($lh['performance']) ? (int) $lh['performance'] : null,
            'accessibility'  => isset($lh['accessibility']) ? (int) $lh['accessibility'] : null,
            'seo'            => isset($lh['seo']) ? (int) $lh['seo'] : null,
        ];
    }
}
