<?php

namespace Tests\Unit;

use App\Models\Prospect;
use App\Services\CombineScoresService;
use PHPUnit\Framework\TestCase;

class CombineScoresServiceTest extends TestCase
{
    private CombineScoresService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CombineScoresService();
    }

    public function test_gbp_only_uses_gbp_score(): void
    {
        $prospect = new Prospect(['gbp_score' => 80, 'a11y_score' => 20]);

        $result = $this->service->combine($prospect, 'gbp_only');

        $this->assertEquals(80, $result['combined_score']);
        $this->assertEquals('gbp', $result['dominant_angle']);
    }

    public function test_accessibility_only_uses_a11y_score(): void
    {
        $prospect = new Prospect(['gbp_score' => 80, 'a11y_score' => 45]);

        $result = $this->service->combine($prospect, 'accessibility_only');

        $this->assertEquals(45, $result['combined_score']);
        $this->assertEquals('accessibility', $result['dominant_angle']);
    }

    public function test_combined_averages_scores(): void
    {
        $prospect = new Prospect(['gbp_score' => 80, 'a11y_score' => 40]);

        $result = $this->service->combine($prospect, 'combined');

        $this->assertEquals(60, $result['combined_score']);
        $this->assertEquals('gbp', $result['dominant_angle']);
    }

    public function test_combined_marks_both_when_scores_are_close(): void
    {
        $prospect = new Prospect(['gbp_score' => 50, 'a11y_score' => 55]);

        $result = $this->service->combine($prospect, 'combined');

        $this->assertEquals(53, $result['combined_score']);
        $this->assertEquals('both', $result['dominant_angle']);
    }
}
