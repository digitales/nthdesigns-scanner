<?php

namespace Tests\Unit;

use App\Enums\ScanType;
use App\Services\Reports\ReportContextBuilder;
use Tests\TestCase;

class ReportContextBuilderTest extends TestCase
{
    private ReportContextBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new ReportContextBuilder;
    }

    public function test_combined_scan_headline_mentions_critical_issues_and_review_gap(): void
    {
        $context = $this->builder->build($this->baseInput([
            'scan_type' => ScanType::Combined->value,
            'violation_summary' => ['critical' => 4, 'serious' => 11, 'moderate' => 8, 'minor' => 0, 'total' => 23],
            'comparison' => ['review_gap' => 77, 'photo_gap' => 5, 'rating_gap' => 0.8],
            'prospect' => ['review_count' => 12, 'photo_count' => 3, 'rating' => 4.0],
            'benchmark' => ['name' => 'Top Dental', 'review_count' => 89, 'photo_count' => 20, 'rating' => 4.8],
        ]));

        $this->assertStringContainsString('booking or enquiry', $context['headline']);
        $this->assertStringContainsString('reviews', strtolower($context['headline']));
        $this->assertSame('4 likely blocking enquiries', $context['severity_labels'][0]['label']);
    }

    public function test_singular_enquiry_label(): void
    {
        $context = $this->builder->build($this->baseInput([
            'violation_summary' => ['critical' => 1, 'serious' => 0, 'moderate' => 0, 'minor' => 0, 'total' => 1],
        ]));

        $this->assertSame('1 likely blocking enquiry', $context['severity_labels'][0]['label']);
    }

    public function test_gbp_only_scan_omits_accessibility_dimension(): void
    {
        $context = $this->builder->build($this->baseInput([
            'scan_type' => ScanType::GbpOnly->value,
            'violation_summary' => ['critical' => 0, 'serious' => 0, 'moderate' => 0, 'minor' => 0, 'total' => 0],
            'top_violations' => [],
            'a11y_score' => 0,
            'comparison' => ['review_gap' => 20, 'photo_gap' => 0, 'rating_gap' => null],
            'benchmark' => ['name' => 'Leader', 'review_count' => 50, 'photo_count' => 10, 'rating' => 4.5],
            'prospect' => ['review_count' => 30, 'photo_count' => 5, 'rating' => 4.0],
        ]));

        $keys = array_column($context['dimensions'], 'key');

        $this->assertNotContains('accessibility', $keys);
        $this->assertContains('gbp', $keys);
    }

    public function test_high_performance_score_yields_low_risk_and_no_lighthouse_captions_at_seventy_plus(): void
    {
        $context = $this->builder->build($this->baseInput([
            'performance_score' => 85,
            'lighthouse' => [
                'performance' => 85,
                'accessibility' => 92,
                'seo' => 90,
                'best_practices' => 88,
            ],
        ]));

        $performance = collect($context['dimensions'])->firstWhere('key', 'performance');

        $this->assertSame('low', $performance['risk']);
        $this->assertSame([], $context['lighthouse_captions']);
    }

    public function test_zero_violations_omits_accessibility_dimension_but_keeps_performance(): void
    {
        $context = $this->builder->build($this->baseInput([
            'violation_summary' => ['critical' => 0, 'serious' => 0, 'moderate' => 0, 'minor' => 0, 'total' => 0],
            'top_violations' => [],
            'performance_score' => 25,
            'lighthouse' => ['performance' => 25, 'accessibility' => null, 'seo' => null, 'best_practices' => null],
            'benchmark' => null,
            'comparison' => [],
        ]));

        $keys = array_column($context['dimensions'], 'key');

        $this->assertNotContains('accessibility', $keys);
        $this->assertContains('performance', $keys);
        $this->assertStringContainsString('slowly on mobile', strtolower($context['headline']));
    }

    public function test_lighthouse_captions_only_for_scores_below_seventy(): void
    {
        $context = $this->builder->build($this->baseInput([
            'lighthouse' => [
                'performance' => 45,
                'accessibility' => 55,
                'seo' => 80,
                'best_practices' => 65,
            ],
        ]));

        $this->assertArrayHasKey('performance', $context['lighthouse_captions']);
        $this->assertArrayHasKey('accessibility', $context['lighthouse_captions']);
        $this->assertArrayNotHasKey('seo', $context['lighthouse_captions']);
        $this->assertArrayHasKey('best_practices', $context['lighthouse_captions']);
    }

    public function test_omits_minor_severity_from_public_labels(): void
    {
        $context = $this->builder->build($this->baseInput([
            'violation_summary' => ['critical' => 0, 'serious' => 2, 'moderate' => 3, 'minor' => 5, 'total' => 10],
        ]));

        $levels = array_column($context['severity_labels'], 'level');

        $this->assertNotContains('minor', $levels);
        $this->assertSame(['serious', 'moderate'], $levels);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function baseInput(array $overrides = []): array
    {
        return array_merge([
            'scan_type' => ScanType::Combined->value,
            'city' => 'Birmingham',
            'niche' => 'dental practice',
            'gbp_score' => 62,
            'a11y_score' => 78,
            'performance_score' => 24,
            'violation_summary' => ['critical' => 4, 'serious' => 11, 'moderate' => 8, 'minor' => 0, 'total' => 23],
            'top_violations' => [
                ['id' => 'color-contrast', 'impact' => 'critical'],
            ],
            'comparison' => ['review_gap' => 77, 'photo_gap' => 17, 'rating_gap' => 0.8],
            'benchmark' => ['name' => 'Top Dental', 'review_count' => 89, 'photo_count' => 20, 'rating' => 4.8],
            'prospect' => ['review_count' => 12, 'photo_count' => 3, 'rating' => 4.0],
            'lighthouse' => [
                'performance' => 24,
                'accessibility' => 55,
                'seo' => 60,
                'best_practices' => 65,
            ],
        ], $overrides);
    }
}
