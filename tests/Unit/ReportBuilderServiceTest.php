<?php

namespace Tests\Unit;

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
}
