<?php

namespace App\Services\Reports;

use App\Enums\ScanType;

final class ReportContextBuilder
{
    private const HEADLINE_MAX_LENGTH = 220;

    /**
     * @param  array{
     *     scan_type: string,
     *     city: string,
     *     niche: string,
     *     gbp_score: int,
     *     a11y_score: int,
     *     performance_score: int,
     *     violation_summary: array{critical?: int, serious?: int, moderate?: int, minor?: int, total?: int},
     *     top_violations: list<array<string, mixed>>,
     *     comparison: array<string, mixed>,
     *     benchmark: array<string, mixed>|null,
     *     prospect: array<string, mixed>,
     *     lighthouse: array<string, mixed>,
     * }  $input
     * @return array{
     *     headline: string,
     *     severity_labels: list<array{level: string, count: int, label: string}>,
     *     dimensions: list<array{key: string, title: string, risk: string, summary: string}>,
     *     lighthouse_captions: array<string, string>,
     * }
     */
    public function build(array $input): array
    {
        return [
            'headline' => $this->buildHeadline($input),
            'severity_labels' => $this->buildSeverityLabels($input['violation_summary']),
            'dimensions' => $this->buildDimensions($input),
            'lighthouse_captions' => $this->buildLighthouseCaptions($input['lighthouse'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function buildHeadline(array $input): string
    {
        $scanType = $input['scan_type'];
        $summary = $input['violation_summary'];
        $clauses = [];

        if ($this->includesAccessibility($scanType, $input)) {
            $critical = (int) ($summary['critical'] ?? 0);
            $total = (int) ($summary['total'] ?? 0);

            if ($critical > 0) {
                $issueWord = $critical === 1 ? 'issue' : 'issues';
                $clauses[] = "Your site has {$critical} {$issueWord} that may stop visitors from completing a booking or enquiry";
            } elseif ($total > 0) {
                $clauses[] = "Your site has {$total} accessibility issues worth addressing";
            }
        }

        if ($this->includesGbp($scanType, $input)) {
            $gbpClause = $this->gbpHeadlineClause($input);

            if ($gbpClause !== null) {
                $clauses[] = $gbpClause;
            }
        }

        if ($this->includesPerformance($scanType, $input)) {
            $performanceScore = (int) ($input['performance_score'] ?? 0);

            if ($performanceScore > 0 && $performanceScore < 50) {
                $clauses[] = 'the site loads slowly on mobile';
            }
        }

        if ($clauses === []) {
            return 'We found several gaps in your online presence worth addressing before they cost enquiries.';
        }

        $headline = $this->joinHeadlineClauses($clauses);

        return $this->truncateHeadline($headline);
    }

    /**
     * @param  list<string>  $clauses
     */
    private function joinHeadlineClauses(array $clauses): string
    {
        $primary = $clauses[0];

        if (! str_starts_with($primary, 'Your ')) {
            $primary = ucfirst($primary);
        }

        if (count($clauses) === 1) {
            return $primary;
        }

        $secondary = $clauses[1];
        $joiner = str_starts_with($secondary, 'the site') ? ' — and ' : ' — while ';

        return $primary.$joiner.$secondary;
    }

    private function truncateHeadline(string $headline): string
    {
        if (strlen($headline) <= self::HEADLINE_MAX_LENGTH) {
            return $headline;
        }

        return rtrim(substr($headline, 0, self::HEADLINE_MAX_LENGTH - 1)).'…';
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function gbpHeadlineClause(array $input): ?string
    {
        $benchmark = $input['benchmark'] ?? null;

        if ($benchmark === null) {
            return null;
        }

        $comparison = $input['comparison'] ?? [];
        $prospect = $input['prospect'] ?? [];
        $city = $input['city'];
        $niche = $input['niche'];
        $reviewGap = (int) ($comparison['review_gap'] ?? 0);

        if ($reviewGap > 0) {
            $you = (int) ($prospect['review_count'] ?? 0);
            $them = (int) ($benchmark['review_count'] ?? 0);

            return "your Google profile trails the top {$niche} in {$city} on reviews ({$you} vs {$them})";
        }

        $photoGap = (int) ($comparison['photo_gap'] ?? 0);

        if ($photoGap > 0) {
            return "your Google profile has far fewer photos than the top practice in {$city}";
        }

        return null;
    }

    /**
     * @param  array{critical?: int, serious?: int, moderate?: int, minor?: int, total?: int}  $summary
     * @return list<array{level: string, count: int, label: string}>
     */
    private function buildSeverityLabels(array $summary): array
    {
        $labels = [];

        foreach (['critical' => 'likely blocking enquiries', 'serious' => 'serious', 'moderate' => 'moderate'] as $level => $suffix) {
            $count = (int) ($summary[$level] ?? 0);

            if ($count <= 0) {
                continue;
            }

            if ($level === 'critical') {
                $enquiryWord = $count === 1 ? 'enquiry' : 'enquiries';
                $label = "{$count} likely blocking {$enquiryWord}";
            } else {
                $label = "{$count} {$suffix}";
            }

            $labels[] = [
                'level' => $level,
                'count' => $count,
                'label' => $label,
            ];
        }

        return $labels;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array{key: string, title: string, risk: string, summary: string}>
     */
    private function buildDimensions(array $input): array
    {
        $dimensions = [];

        if ($this->hasAccessibilityContent($input) && $this->includesAccessibility($input['scan_type'], $input)) {
            $dimensions[] = [
                'key' => 'accessibility',
                'title' => 'Accessibility',
                'risk' => $this->weaknessRiskBand((int) ($input['a11y_score'] ?? 0)),
                'summary' => $this->accessibilitySummary($input),
            ];
        }

        if ($this->includesGbp($input['scan_type'], $input) && ($input['benchmark'] ?? null) !== null) {
            $dimensions[] = [
                'key' => 'gbp',
                'title' => 'Google profile',
                'risk' => $this->weaknessRiskBand((int) ($input['gbp_score'] ?? 0)),
                'summary' => $this->gbpSummary($input),
            ];
        }

        if ($this->hasPerformanceContent($input) && $this->includesPerformance($input['scan_type'], $input)) {
            $dimensions[] = [
                'key' => 'performance',
                'title' => 'Site speed',
                'risk' => $this->performanceRiskBand((int) ($input['performance_score'] ?? 0)),
                'summary' => $this->performanceSummary((int) ($input['performance_score'] ?? 0)),
            ];
        }

        return $dimensions;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function hasAccessibilityContent(array $input): bool
    {
        $summary = $input['violation_summary'] ?? [];
        $total = (int) ($summary['total'] ?? 0);
        $topViolations = $input['top_violations'] ?? [];

        return $total > 0 || count($topViolations) > 0;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function hasPerformanceContent(array $input): bool
    {
        $performanceScore = (int) ($input['performance_score'] ?? 0);
        $lighthousePerformance = $input['lighthouse']['performance'] ?? null;

        return $performanceScore > 0 || $lighthousePerformance !== null;
    }

    private function includesAccessibility(string $scanType, array $input): bool
    {
        if (! $this->hasAccessibilityContent($input)) {
            return false;
        }

        return $scanType !== ScanType::GbpOnly->value;
    }

    private function includesGbp(string $scanType, array $input): bool
    {
        if ($scanType === ScanType::AccessibilityOnly->value) {
            return false;
        }

        return ($input['benchmark'] ?? null) !== null;
    }

    private function includesPerformance(string $scanType, array $input): bool
    {
        if (! $this->hasPerformanceContent($input)) {
            return false;
        }

        if ($scanType === ScanType::AccessibilityOnly->value) {
            return true;
        }

        return $scanType !== ScanType::GbpOnly->value;
    }

    private function weaknessRiskBand(int $weaknessScore): string
    {
        if ($weaknessScore >= 71) {
            return 'high';
        }

        if ($weaknessScore >= 41) {
            return 'moderate';
        }

        return 'low';
    }

    private function performanceRiskBand(int $performanceScore): string
    {
        if ($performanceScore > 0 && $performanceScore < 30) {
            return 'high';
        }

        if ($performanceScore > 0 && $performanceScore < 50) {
            return 'moderate';
        }

        return 'low';
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function accessibilitySummary(array $input): string
    {
        $ruleSummaries = [
            'color-contrast' => 'Text and buttons may be hard to read',
            'image-alt' => 'Images missing descriptions for screen reader users',
            'label' => 'Forms or links may be unusable with assistive tech',
            'link-name' => 'Forms or links may be unusable with assistive tech',
            'button-name' => 'Forms or links may be unusable with assistive tech',
        ];

        foreach ($input['top_violations'] ?? [] as $violation) {
            $id = $violation['id'] ?? null;

            if ($id !== null && isset($ruleSummaries[$id])) {
                return $ruleSummaries[$id];
            }
        }

        $summary = $input['violation_summary'] ?? [];
        $critical = (int) ($summary['critical'] ?? 0);
        $serious = (int) ($summary['serious'] ?? 0);

        return "{$critical} critical and {$serious} serious issues detected";
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function gbpSummary(array $input): string
    {
        $benchmark = $input['benchmark'] ?? [];
        $comparison = $input['comparison'] ?? [];
        $prospect = $input['prospect'] ?? [];
        $city = $input['city'];
        $reviewGap = (int) ($comparison['review_gap'] ?? 0);

        if ($reviewGap > 0) {
            $you = (int) ($prospect['review_count'] ?? 0);
            $them = (int) ($benchmark['review_count'] ?? 0);

            return "Behind local leader on reviews ({$you} vs {$them})";
        }

        $photoGap = (int) ($comparison['photo_gap'] ?? 0);

        if ($photoGap > 0) {
            $you = (int) ($prospect['photo_count'] ?? 0);
            $them = (int) ($benchmark['photo_count'] ?? 0);
            $name = $benchmark['name'] ?? 'local leader';

            return "Far fewer photos than {$name} ({$you} vs {$them})";
        }

        $ratingGap = $comparison['rating_gap'] ?? null;

        if ($ratingGap !== null && (float) $ratingGap > 0) {
            $you = number_format((float) ($prospect['rating'] ?? 0), 1);
            $them = number_format((float) ($benchmark['rating'] ?? 0), 1);

            return "Lower rating than local leader ({$you} vs {$them})";
        }

        return "Several GBP gaps vs the top practice in {$city}";
    }

    private function performanceSummary(int $performanceScore): string
    {
        if ($performanceScore > 0 && $performanceScore < 30) {
            return 'Very slow on mobile — many visitors leave before the page loads';
        }

        if ($performanceScore > 0 && $performanceScore < 50) {
            return 'Slow on mobile — may affect search rankings';
        }

        return 'Acceptable load times; room to improve';
    }

    /**
     * @param  array<string, mixed>  $lighthouse
     * @return array<string, string>
     */
    private function buildLighthouseCaptions(array $lighthouse): array
    {
        $templates = [
            'performance' => 'Below 50 — Google starts penalising mobile search',
            'accessibility' => 'Automated check only; manual audit may find more',
            'seo' => 'Below 50 — harder to rank for local searches',
            'best_practices' => 'Below 50 — security or browser compatibility gaps',
        ];

        $captions = [];

        foreach ($templates as $key => $caption) {
            $score = $lighthouse[$key] ?? null;

            if ($score !== null && (int) $score < 70) {
                $captions[$key] = $caption;
            }
        }

        return $captions;
    }
}
