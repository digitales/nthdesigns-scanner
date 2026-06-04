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
        $prospect = new Prospect(['gbp_score' => 80, 'a11y_score' => 20, 'performance_score' => 25]);

        $result = $this->service->combine($prospect, 'gbp_only');

        $this->assertSame(80, $result['combined_score']);
        $this->assertSame('gbp', $result['dominant_angle']);
    }

    public function test_gbp_only_upgrades_to_combined_when_site_audit_present(): void
    {
        $prospect = new Prospect([
            'gbp_score' => 68,
            'a11y_score' => 45,
            'performance_score' => 30,
            'raw_a11y_payload' => ['url' => 'https://example.com', 'violations' => []],
        ]);

        $this->assertSame('combined', $this->service->effectiveScanType($prospect, 'gbp_only'));

        $result = $this->service->combineForProspect($prospect, 'gbp_only');

        // round(23.8 + 22.5 + 10.5) = 57
        $this->assertSame(57, $result['combined_score']);
        $this->assertSame('both', $result['dominant_angle']);
    }

    public function test_gbp_only_stays_gbp_only_without_site_audit_payload(): void
    {
        $prospect = new Prospect([
            'gbp_score' => 68,
            'a11y_score' => 0,
            'raw_a11y_payload' => null,
        ]);

        $this->assertSame('gbp_only', $this->service->effectiveScanType($prospect, 'gbp_only'));
    }

    public function test_accessibility_only_uses_a11y_score(): void
    {
        $prospect = new Prospect(['gbp_score' => 80, 'a11y_score' => 45]);

        $result = $this->service->combine($prospect, 'accessibility_only');

        $this->assertSame(45, $result['combined_score']);
        $this->assertSame('accessibility', $result['dominant_angle']);
    }

    public function test_combined_uses_weighted_formula_with_performance_weakness(): void
    {
        // gbp=80, a11y=40, perf=25 -> weakness=75
        // round(28 + 20 + 11.25) = 59
        $prospect = new Prospect([
            'gbp_score' => 80,
            'a11y_score' => 40,
            'performance_score' => 25,
        ]);

        $result = $this->service->combine($prospect, 'combined');

        $this->assertSame(59, $result['combined_score']);
        $this->assertSame('gbp', $result['dominant_angle']);
    }

    public function test_combined_dominant_accessibility_when_a11y_above_70(): void
    {
        $prospect = new Prospect([
            'gbp_score' => 50,
            'a11y_score' => 75,
            'performance_score' => 80,
        ]);

        $result = $this->service->combine($prospect, 'combined');

        $this->assertSame('accessibility', $result['dominant_angle']);
    }

    public function test_combined_dominant_both_when_neither_above_70(): void
    {
        $prospect = new Prospect([
            'gbp_score' => 50,
            'a11y_score' => 55,
            'performance_score' => 0,
        ]);

        $result = $this->service->combine($prospect, 'combined');

        $this->assertSame('both', $result['dominant_angle']);
    }

    public function test_performance_weakness_is_zero_when_no_lighthouse_score(): void
    {
        $prospect = new Prospect([
            'gbp_score' => 40,
            'a11y_score' => 40,
            'performance_score' => 0,
        ]);

        $result = $this->service->combine($prospect, 'combined');

        // round(14 + 20 + 0) = 34
        $this->assertSame(34, $result['combined_score']);
    }
}
