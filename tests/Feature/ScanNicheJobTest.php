<?php

namespace Tests\Feature;

use App\Jobs\ScanNicheJob;
use App\Models\NicheScan;
use App\Services\GbpScoringService;
use App\Services\GooglePlacesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScanNicheJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_completes_scan_with_aggregates_and_opportunity_score(): void
    {
        config(['services.google_places.key' => 'test-key']);

        Http::fake([
            'https://places.googleapis.com/v1/places:searchText' => Http::response([
                'places' => [
                    ['id' => 'places/a'],
                    ['id' => 'places/b'],
                ],
            ], 200),
            'https://places.googleapis.com/v1/places/places/*' => Http::sequence()
                ->push([
                    'id' => 'places/a',
                    'displayName' => ['text' => 'A'],
                    'userRatingCount' => 5,
                    'photos' => [],
                ], 200)
                ->push([
                    'id' => 'places/b',
                    'displayName' => ['text' => 'B'],
                    'websiteUri' => 'https://b.example',
                    'userRatingCount' => 100,
                    'photos' => array_fill(0, 6, []),
                    'editorialSummary' => ['text' => 'Desc'],
                    'regularOpeningHours' => ['periods' => [['open' => ['day' => 1]]]],
                    'rating' => 4.5,
                    'nationalPhoneNumber' => '+441234',
                ], 200),
        ]);

        (new ScanNicheJob(
            niche: 'Dental Practice',
            nicheQuery: 'dental practice',
            city: 'Birmingham',
            country: 'GB',
            sample: 2,
            scanDate: '2026-05-27',
        ))->handle(
            app(GooglePlacesService::class),
            app(GbpScoringService::class),
        );

        $row = NicheScan::query()->first();

        $this->assertNotNull($row);
        $this->assertSame('complete', $row->status);
        $this->assertSame(2, $row->result_count);
        $this->assertSame(2, $row->sampled_count);
        $this->assertGreaterThan(0, $row->avg_gbp_score);
        $this->assertSame(50.0, $row->pct_no_website);
        $this->assertSame(50.0, $row->pct_low_reviews);
        $this->assertNotNull($row->opportunity_score);
        $this->assertNotNull($row->ran_at);
    }

    public function test_zero_results_completes_with_opportunity_score_zero(): void
    {
        config(['services.google_places.key' => 'test-key']);

        Http::fake([
            'https://places.googleapis.com/v1/places:searchText' => Http::response(['places' => []], 200),
        ]);

        (new ScanNicheJob(
            niche: 'Dental Practice',
            nicheQuery: 'dental practice',
            city: 'Birmingham',
            country: 'GB',
            sample: 5,
            scanDate: '2026-05-27',
        ))->handle(
            app(GooglePlacesService::class),
            app(GbpScoringService::class),
        );

        $row = NicheScan::query()->first();

        $this->assertSame('complete', $row->status);
        $this->assertSame(0, $row->result_count);
        $this->assertSame(0, $row->sampled_count);
        $this->assertSame(0.0, $row->opportunity_score);
    }
}
