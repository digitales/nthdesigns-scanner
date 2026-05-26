<?php

namespace Tests\Unit;

use App\Services\GbpScoringService;
use PHPUnit\Framework\TestCase;

class GbpScoringServiceTest extends TestCase
{
    private GbpScoringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GbpScoringService();
    }

    public function test_empty_profile_scores_maximum(): void
    {
        $payload = [];
        $result = $this->service->score($payload);

        $this->assertEquals(70, $result['score']); // 25+15+10+10+10 = 70 (no rating to penalise)
        $this->assertContains('Under 20 reviews', $result['flags']);
        $this->assertContains('No photos uploaded', $result['flags']);
        $this->assertContains('No website listed', $result['flags']);
        $this->assertContains('Missing business description', $result['flags']);
        $this->assertContains('Opening hours not set', $result['flags']);
    }

    public function test_strong_profile_scores_zero(): void
    {
        $payload = [
            'userRatingCount'     => 200,
            'rating'              => 4.5,
            'photos'              => array_fill(0, 20, []),
            'websiteUri'          => 'https://example.com',
            'editorialSummary'    => ['text' => 'A great business'],
            'regularOpeningHours' => ['periods' => [['open' => ['day' => 1]]]],
        ];

        $result = $this->service->score($payload);
        $this->assertEquals(0, $result['score']);
        $this->assertEmpty($result['flags']);
    }

    public function test_partial_review_count_scores_correctly(): void
    {
        $payload = ['userRatingCount' => 35];
        $result = $this->service->score($payload);

        $this->assertContains('Fewer than 50 reviews', $result['flags']);
        $this->assertNotContains('Under 20 reviews', $result['flags']);
    }

    public function test_low_rating_adds_points(): void
    {
        $payload = ['rating' => 3.0];
        $result = $this->service->score($payload);

        $this->assertContains('Rating below 3.5 stars', $result['flags']);
    }
}
