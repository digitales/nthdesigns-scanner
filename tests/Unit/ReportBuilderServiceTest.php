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
}
