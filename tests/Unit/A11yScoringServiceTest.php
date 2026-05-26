<?php

namespace Tests\Unit;

use App\Services\A11yScoringService;
use PHPUnit\Framework\TestCase;

class A11yScoringServiceTest extends TestCase
{
    private A11yScoringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new A11yScoringService();
    }

    public function test_critical_violations_score_high(): void
    {
        $payload = [
            'violations' => [
                ['impact' => 'critical'],
                ['impact' => 'critical'],
            ],
        ];

        $result = $this->service->score($payload);

        $this->assertGreaterThanOrEqual(30, $result['score']);
        $this->assertStringContainsString('critical', $result['flags'][0]);
    }

    public function test_clean_site_scores_low(): void
    {
        $payload = [
            'violations' => [],
            'lighthouse' => ['performance' => 95, 'accessibility' => 95],
        ];

        $result = $this->service->score($payload);

        $this->assertEquals(0, $result['score']);
    }

    public function test_audit_error_returns_moderate_score(): void
    {
        $result = $this->service->score(['error' => 'timeout']);

        $this->assertEquals(50, $result['score']);
        $this->assertContains('Site audit failed to load', $result['flags']);
    }

    public function test_low_lighthouse_performance_alone_does_not_add_score(): void
    {
        $payload = [
            'violations' => [],
            'lighthouse' => ['performance' => 40],
        ];

        $result = $this->service->score($payload);

        $this->assertSame(0, $result['score']);
        $this->assertNotContains('Performance score below 50', $result['flags']);
    }
}
