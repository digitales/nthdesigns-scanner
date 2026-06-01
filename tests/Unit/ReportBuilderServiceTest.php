<?php

namespace Tests\Unit;

use App\Models\AuditJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Services\ReportBuilderService;
use Tests\TestCase;

class ReportBuilderServiceTest extends TestCase
{
    private ReportBuilderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReportBuilderService();
    }

    public function test_builds_prospect_and_benchmark_data(): void
    {
        $search = new Search([
            'niche'     => 'dental practice',
            'city'      => 'Birmingham',
            'country'   => 'GB',
            'scan_type' => 'combined',
        ]);

        $prospect = new Prospect([
            'search_id'     => 1,
            'business_name' => 'Test Dental',
            'review_count'  => 10,
            'photo_count'   => 2,
            'rating'        => 4.0,
            'gbp_score'     => 60,
            'a11y_score'    => 40,
            'combined_score'=> 50,
            'gbp_flags'     => ['Under 20 reviews'],
            'a11y_flags'    => [],
            'dominant_angle'=> 'gbp',
        ]);
        $prospect->setRelation('search', $search);

        $benchmark = [
            'id' => 'places/123',
            'displayName' => ['text' => 'Top Dental'],
            'rating' => 4.8,
            'userRatingCount' => 120,
            'photos' => array_fill(0, 15, []),
        ];

        $report = $this->service->build($prospect, $benchmark);

        $this->assertEquals('dental practice', $report['niche']);
        $this->assertEquals('Test Dental', $report['prospect']['business_name']);
        $this->assertEquals('Top Dental', $report['benchmark']['name']);
        $this->assertEquals(110, $report['comparison']['review_gap']);
        $this->assertEquals(13, $report['comparison']['photo_gap']);
        $this->assertArrayHasKey('grade', $report);
        $this->assertArrayNotHasKey('combined_score', $report['prospect']);
    }

    public function test_extracts_top_violations_and_grade(): void
    {
        $search = new Search(['niche' => 'test', 'city' => 'Leeds', 'country' => 'GB', 'scan_type' => 'combined']);
        $prospect = new Prospect([
            'business_name'  => 'Acme',
            'combined_score' => 80,
            'gbp_score'      => 70,
            'a11y_score'     => 90,
            'raw_a11y_payload' => [
                'violations' => [
                    [
                        'id' => 'color-contrast',
                        'impact' => 'critical',
                        'description' => 'Elements must have sufficient color contrast',
                        'help' => 'Fix contrast',
                        'tags' => ['wcag2aa'],
                        'nodes' => [1, 2],
                    ],
                ],
            ],
            'raw_lighthouse_payload' => [
                'performance' => 42,
                'accessibility' => 55,
            ],
        ]);
        $prospect->setRelation('search', $search);

        $report = $this->service->build($prospect, null);

        $this->assertSame('C', $report['grade']);
        $this->assertSame(1, $report['violation_summary']['critical']);
        $this->assertCount(1, $report['top_violations']);
        $this->assertSame(42, $report['lighthouse']['performance']);
    }

    public function test_extract_all_violations_sorted_by_impact(): void
    {
        $payload = [
            'violations' => [
                ['id' => 'minor-rule', 'impact' => 'minor', 'description' => 'Minor issue', 'nodes' => []],
                ['id' => 'critical-rule', 'impact' => 'critical', 'description' => 'Critical issue', 'nodes' => []],
                ['id' => 'serious-rule', 'impact' => 'serious', 'description' => 'Serious issue', 'nodes' => []],
            ],
        ];

        $all = $this->service->extractAllViolations($payload);

        $this->assertSame(['critical-rule', 'serious-rule', 'minor-rule'], array_column($all, 'id'));
    }

    public function test_extract_all_violations_returns_empty_for_no_violations(): void
    {
        $this->assertSame([], $this->service->extractAllViolations([]));
        $this->assertSame([], $this->service->extractAllViolations(['violations' => []]));
    }

    public function test_build_operator_audit_returns_null_when_not_complete(): void
    {
        $prospect = new Prospect([
            'audit_status' => 'pending',
            'raw_a11y_payload' => ['violations' => [['id' => 'x', 'impact' => 'critical', 'nodes' => [1]]]],
            'website_url' => 'https://example.com',
        ]);

        $this->assertNull($this->service->buildOperatorAudit($prospect));
    }

    public function test_lighthouse_for_prospect_uses_stored_payload(): void
    {
        $prospect = new Prospect([
            'performance_score' => 42,
            'raw_lighthouse_payload' => [
                'performance' => 42,
                'accessibility' => 55,
                'seo' => 70,
            ],
        ]);

        $lighthouse = $this->service->lighthouseForProspect($prospect);

        $this->assertSame(42, $lighthouse['performance']);
        $this->assertSame(55, $lighthouse['accessibility']);
        $this->assertSame(70, $lighthouse['seo']);
    }

    public function test_lighthouse_for_prospect_falls_back_to_performance_score(): void
    {
        $prospect = new Prospect([
            'performance_score' => 20,
            'raw_lighthouse_payload' => null,
        ]);

        $lighthouse = $this->service->lighthouseForProspect($prospect);

        $this->assertSame(20, $lighthouse['performance']);
        $this->assertNull($lighthouse['accessibility']);
    }

    public function test_lighthouse_for_prospect_returns_null_when_no_metrics(): void
    {
        $prospect = new Prospect([
            'performance_score' => 0,
            'raw_lighthouse_payload' => null,
        ]);

        $this->assertNull($this->service->lighthouseForProspect($prospect));
    }

    public function test_build_operator_audit_returns_null_when_payload_missing(): void
    {
        $prospect = new Prospect([
            'audit_status' => 'complete',
            'raw_a11y_payload' => null,
            'raw_lighthouse_payload' => null,
            'website_url' => 'https://example.com',
        ]);

        $this->assertNull($this->service->buildOperatorAudit($prospect));
    }

    public function test_build_operator_audit_returns_full_shape_when_complete(): void
    {
        $completedAt = now()->subHour();
        $prospect = new Prospect([
            'audit_status' => 'complete',
            'website_url' => 'https://example.com',
            'performance_score' => 42,
            'raw_a11y_payload' => [
                'url' => 'https://example.com',
                'violations' => [
                    ['id' => 'color-contrast', 'impact' => 'critical', 'description' => 'Contrast', 'tags' => ['wcag2aa'], 'nodes' => [1]],
                ],
                'pass_count' => 40,
                'incomplete_count' => 2,
            ],
            'raw_lighthouse_payload' => [
                'performance' => 42,
                'accessibility' => 55,
                'seo' => 70,
            ],
        ]);
        $prospect->setRelation('auditJobs', collect([
            new AuditJob([
                'job_type' => 'accessibility',
                'status' => 'complete',
                'completed_at' => $completedAt,
            ]),
        ]));

        $audit = $this->service->buildOperatorAudit($prospect);

        $this->assertNotNull($audit);
        $this->assertSame('https://example.com', $audit['url']);
        $this->assertSame(1, $audit['summary']['critical']);
        $this->assertSame(40, $audit['pass_count']);
        $this->assertSame(2, $audit['incomplete_count']);
        $this->assertCount(1, $audit['top_violations']);
        $this->assertCount(1, $audit['all_violations']);
        $this->assertSame(42, $audit['lighthouse']['performance']);
        $this->assertSame(42, $audit['performance_score']);
        $this->assertSame($completedAt->toIso8601String(), $audit['audited_at']);
    }

    public function test_extract_top_violations_includes_screenshot_url(): void
    {
        $payload = [
            'violations' => [
                ['id' => 'color-contrast', 'impact' => 'critical', 'description' => 'Contrast fail', 'nodes' => [1]],
            ],
            'violation_screenshots' => [
                ['violation_id' => 'color-contrast', 'url' => 'https://example.com/violation-0.png'],
            ],
        ];

        $top = $this->service->extractTopViolations($payload, 5);

        $this->assertSame('https://example.com/violation-0.png', $top[0]['screenshot_url']);
    }

    public function test_health_to_grade_mapping(): void
    {
        $this->assertSame('B+', $this->service->healthToGrade(90));
        $this->assertSame('D', $this->service->healthToGrade(10));
    }

    public function test_combined_to_grade_mapping(): void
    {
        $this->assertSame('D', $this->service->combinedToGrade(87));
        $this->assertSame('C', $this->service->combinedToGrade(75));
        $this->assertSame('C+', $this->service->combinedToGrade(55));
        $this->assertSame('B', $this->service->combinedToGrade(35));
        $this->assertSame('B+', $this->service->combinedToGrade(15));
    }

    public function test_builds_without_benchmark(): void
    {
        $search = new Search(['niche' => 'plumber', 'city' => 'Leeds', 'country' => 'GB', 'scan_type' => 'gbp_only']);
        $prospect = new Prospect(['business_name' => 'Quick Fix', 'gbp_score' => 30]);
        $prospect->setRelation('search', $search);

        $report = $this->service->build($prospect, null);

        $this->assertNull($report['benchmark']);
        $this->assertEmpty($report['comparison']);
    }

    public function test_top_violations_include_user_impact_and_fix_hint(): void
    {
        $search = new Search(['niche' => 'test', 'city' => 'Leeds', 'country' => 'GB', 'scan_type' => 'combined']);
        $prospect = new Prospect([
            'business_name' => 'Acme',
            'combined_score' => 80,
            'raw_a11y_payload' => [
                'violations' => [
                    [
                        'id' => 'color-contrast',
                        'impact' => 'critical',
                        'description' => 'Contrast fail',
                        'nodes' => [1],
                    ],
                ],
            ],
        ]);
        $prospect->setRelation('search', $search);

        $report = $this->service->build($prospect, null);

        $this->assertArrayHasKey('user_impact', $report['top_violations'][0]);
        $this->assertArrayHasKey('fix_hint', $report['top_violations'][0]);
        $this->assertStringContainsString('contrast', strtolower($report['top_violations'][0]['fix_hint']));
    }

    public function test_lighthouse_includes_best_practices(): void
    {
        $search = new Search(['niche' => 'test', 'city' => 'Leeds', 'country' => 'GB', 'scan_type' => 'combined']);
        $prospect = new Prospect([
            'business_name' => 'Acme',
            'combined_score' => 50,
            'raw_lighthouse_payload' => [
                'performance' => 50,
                'best_practices' => 88,
            ],
        ]);
        $prospect->setRelation('search', $search);

        $report = $this->service->build($prospect, null);

        $this->assertSame(88, $report['lighthouse']['best_practices']);
    }

    public function test_build_operator_page_speed_returns_null_when_not_complete(): void
    {
        $prospect = new Prospect([
            'audit_status' => 'pending',
            'performance_score' => 50,
            'raw_lighthouse_payload' => ['performance' => 50, 'metrics' => []],
        ]);
        $prospect->setRelation('search', new Search(['scan_type' => 'combined']));

        $this->assertNull($this->service->buildOperatorPageSpeed($prospect));
    }

    public function test_build_operator_page_speed_returns_null_for_gbp_only(): void
    {
        $prospect = new Prospect([
            'audit_status' => 'complete',
            'performance_score' => 50,
            'raw_lighthouse_payload' => [
                'performance' => 50,
                'metrics' => ['lcp' => ['display' => '3.2 s', 'rating' => 'poor']],
                'opportunities' => [],
            ],
        ]);
        $prospect->setRelation('search', new Search(['scan_type' => 'gbp_only']));

        $this->assertNull($this->service->buildOperatorPageSpeed($prospect));
    }

    public function test_build_operator_page_speed_returns_null_for_legacy_score_only_payload(): void
    {
        $prospect = new Prospect([
            'audit_status' => 'complete',
            'performance_score' => 28,
            'website_url' => 'https://example.com',
            'raw_lighthouse_payload' => ['performance' => 28, 'accessibility' => 60, 'seo' => 70],
            'raw_a11y_payload' => ['url' => 'https://example.com'],
        ]);
        $prospect->setRelation('search', new Search(['scan_type' => 'combined']));

        $this->assertNull($this->service->buildOperatorPageSpeed($prospect));
    }

    public function test_build_operator_page_speed_returns_full_shape_with_highlights(): void
    {
        $completedAt = now()->subHour();
        $prospect = new Prospect([
            'audit_status' => 'complete',
            'website_url' => 'https://example.com',
            'performance_score' => 28,
            'raw_a11y_payload' => ['url' => 'https://example.com'],
            'raw_lighthouse_payload' => [
                'performance' => 28,
                'metrics' => [
                    'lcp' => ['display' => '3.2 s', 'rating' => 'poor'],
                    'inp' => ['display' => '180 ms', 'rating' => 'good'],
                    'cls' => ['display' => '0.14', 'rating' => 'needs_improvement'],
                    'fcp' => ['display' => '1.8 s', 'rating' => 'needs_improvement'],
                ],
                'opportunities' => [
                    [
                        'id' => 'unused-javascript',
                        'title' => 'Reduce unused JavaScript',
                        'description' => 'Remove unused JavaScript.',
                        'savings_ms' => 1200,
                        'savings_display' => 'Est. savings 1.2 s',
                    ],
                    [
                        'id' => 'render-blocking-resources',
                        'title' => 'Eliminate render-blocking resources',
                        'description' => 'Resources are blocking the first paint.',
                        'savings_ms' => 450,
                        'savings_display' => 'Est. savings 450 ms',
                    ],
                ],
            ],
        ]);
        $prospect->setRelation('search', new Search(['scan_type' => 'combined']));
        $prospect->setRelation('auditJobs', collect([
            new AuditJob([
                'job_type' => 'accessibility',
                'status' => 'complete',
                'completed_at' => $completedAt,
            ]),
        ]));

        $pageSpeed = $this->service->buildOperatorPageSpeed($prospect);

        $this->assertNotNull($pageSpeed);
        $this->assertTrue($pageSpeed['has_detail']);
        $this->assertSame('3.2 s', $pageSpeed['metrics']['lcp']['display']);
        $this->assertCount(2, $pageSpeed['opportunities']);
        $this->assertTrue($pageSpeed['opportunities'][0]['highlight']);
        $this->assertFalse($pageSpeed['opportunities'][1]['highlight']);
        $this->assertSame($completedAt->toIso8601String(), $pageSpeed['audited_at']);
    }

    public function test_benchmark_includes_description_and_hours(): void
    {
        $search = new Search(['niche' => 'dental', 'city' => 'Birmingham', 'country' => 'GB', 'scan_type' => 'combined']);
        $prospect = new Prospect([
            'business_name' => 'Test Dental',
            'has_description' => false,
            'hours_complete' => true,
            'review_count' => 5,
            'photo_count' => 1,
            'combined_score' => 70,
        ]);
        $prospect->setRelation('search', $search);

        $benchmark = [
            'id' => 'places/1',
            'displayName' => ['text' => 'Top Dental'],
            'rating' => 4.9,
            'userRatingCount' => 100,
            'photos' => array_fill(0, 10, []),
            'editorialSummary' => ['text' => 'A great practice'],
            'regularOpeningHours' => ['periods' => [['open' => '0900']]],
        ];

        $report = $this->service->build($prospect, $benchmark);

        $this->assertFalse($report['prospect']['has_description']);
        $this->assertTrue($report['prospect']['hours_complete']);
        $this->assertTrue($report['benchmark']['has_description']);
        $this->assertTrue($report['benchmark']['hours_complete']);
    }

    public function test_cms_for_prospect_returns_null_without_website(): void
    {
        $prospect = new Prospect(['website_url' => null]);

        $this->assertNull($this->service->cmsForProspect($prospect));
    }

    public function test_cms_for_prospect_pending_when_url_but_no_detection(): void
    {
        $prospect = new Prospect([
            'website_url' => 'https://example.com',
            'cms_detection' => null,
        ]);

        $cms = $this->service->cmsForProspect($prospect);

        $this->assertTrue($cms['pending']);
        $this->assertNull($cms['badge']);
    }

    public function test_cms_for_prospect_labels_wordpress_with_version(): void
    {
        $prospect = new Prospect([
            'website_url' => 'https://example.com',
            'cms_detection' => [
                'platform' => 'wordpress',
                'version' => '6.4.2',
                'confidence' => 'high',
                'signals' => [],
                'detected_at' => '2026-06-01T00:00:00+00:00',
                'url' => 'https://example.com',
            ],
        ]);

        $cms = $this->service->cmsForProspect($prospect);

        $this->assertSame('WordPress 6.4', $cms['label']);
        $this->assertSame('WP', $cms['badge']);
        $this->assertFalse($cms['pending']);
    }

    public function test_build_omits_cms_from_public_report(): void
    {
        $search = new Search([
            'niche' => 'plumber',
            'city' => 'London',
            'country' => 'GB',
            'scan_type' => 'combined',
        ]);
        $prospect = new Prospect([
            'website_url' => 'https://example.com',
            'cms_detection' => ['platform' => 'wordpress', 'confidence' => 'high'],
            'combined_score' => 50,
            'review_count' => 0,
            'photo_count' => 0,
        ]);
        $prospect->setRelation('search', $search);

        $report = $this->service->build($prospect, null);

        $this->assertArrayNotHasKey('cms', $report);
    }
}
