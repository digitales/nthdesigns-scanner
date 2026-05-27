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

    private function benchmarkFixture(): array
    {
        return [
            'place_id' => 'places/leader',
            'name' => 'Top Dental',
            'review_count' => 300,
            'photo_count' => 40,
            'rating' => 4.9,
            'has_description' => true,
            'hours_complete' => true,
        ];
    }

    public function test_empty_profile_scores_maximum(): void
    {
        $payload = [];
        $result = $this->service->score($payload);

        $this->assertEquals(78, $result['score']);
        $this->assertContains('Under 20 reviews', $result['flags']);
        $this->assertContains('No photos uploaded', $result['flags']);
        $this->assertContains('No website listed', $result['flags']);
        $this->assertContains('Missing business description', $result['flags']);
        $this->assertContains('Opening hours not set', $result['flags']);
        $this->assertContains('No phone number listed', $result['flags']);
    }

    public function test_strong_profile_scores_zero(): void
    {
        $payload = [
            'id' => 'places/abc',
            'userRatingCount' => 200,
            'rating' => 4.5,
            'photos' => array_fill(0, 20, []),
            'websiteUri' => 'https://example.com',
            'nationalPhoneNumber' => '+441234567890',
            'editorialSummary' => ['text' => 'A great business'],
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

    public function test_rating_between_3_5_and_4_adds_tier_flag(): void
    {
        $payload = [
            'rating' => 3.8,
            'userRatingCount' => 200,
            'photos' => array_fill(0, 20, []),
            'websiteUri' => 'https://example.com',
            'nationalPhoneNumber' => '+441234567890',
            'editorialSummary' => ['text' => 'Desc'],
            'regularOpeningHours' => ['periods' => [['open' => ['day' => 1]]]],
        ];

        $result = $this->service->score($payload);

        $this->assertContains('Rating below 4 stars', $result['flags']);
        $this->assertNotContains('Rating below 3.5 stars', $result['flags']);
    }

    public function test_five_to_nine_photos_flag(): void
    {
        $payload = ['photos' => array_fill(0, 7, [])];
        $result = $this->service->score($payload);

        $this->assertContains('Fewer than 10 photos', $result['flags']);
        $this->assertNotContains('Fewer than 5 photos', $result['flags']);
    }

    public function test_social_website_host_flag(): void
    {
        $payload = ['websiteUri' => 'https://www.facebook.com/my-business'];
        $result = $this->service->score($payload);

        $this->assertContains('No dedicated website', $result['flags']);
        $this->assertNotContains('No website listed', $result['flags']);
    }

    public function test_non_operational_business_status_flag(): void
    {
        $payload = ['businessStatus' => 'CLOSED_TEMPORARILY'];
        $result = $this->service->score($payload);

        $this->assertContains('Listing not fully operational', $result['flags']);
    }

    public function test_relative_review_gap_flag_includes_counts_and_city(): void
    {
        $payload = [
            'id' => 'places/prospect',
            'userRatingCount' => 42,
            'photos' => array_fill(0, 10, []),
            'websiteUri' => 'https://example.com',
            'nationalPhoneNumber' => '+441234',
            'editorialSummary' => ['text' => 'x'],
            'regularOpeningHours' => ['periods' => [['open' => ['day' => 1]]]],
            'rating' => 4.5,
        ];

        $result = $this->service->score($payload, $this->benchmarkFixture(), 'Birmingham');

        $this->assertContains('42 reviews vs 300 for the top listing in Birmingham', $result['flags']);
    }

    public function test_skips_relative_flags_when_prospect_is_leader(): void
    {
        $payload = ['id' => 'places/leader', 'userRatingCount' => 300];
        $result = $this->service->score($payload, $this->benchmarkFixture(), 'Birmingham');

        $this->assertNotContains('42 reviews vs 300 for the top listing in Birmingham', $result['flags']);
    }

    public function test_does_not_double_description_flag(): void
    {
        $payload = ['id' => 'places/p1', 'userRatingCount' => 5];
        $result = $this->service->score($payload, $this->benchmarkFixture(), 'Leeds');

        $this->assertContains('Missing business description', $result['flags']);
        $this->assertNotContains('No description while top listing in Leeds has one', $result['flags']);
    }

    public function test_score_capped_at_100(): void
    {
        $result = $this->service->score([], $this->benchmarkFixture(), 'Birmingham');
        $this->assertLessThanOrEqual(100, $result['score']);
    }
}
