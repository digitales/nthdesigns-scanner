<?php

namespace Tests\Feature;

use App\Jobs\ScanNicheJob;
use App\Models\IgnoredNiche;
use App\Models\NicheScan;
use App\Services\NicheExclusionService;
use App\Services\NicheSampleCollector;
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
            app(NicheSampleCollector::class),
            app(NicheExclusionService::class),
        );

        $row = NicheScan::query()->first();

        $this->assertNotNull($row);
        $this->assertSame('complete', $row->status);
        $this->assertSame(2, $row->result_count);
        $this->assertSame(2, $row->sampled_count);
        $this->assertGreaterThan(0, $row->avg_gbp_score);
        $this->assertSame(50.0, $row->pct_no_website);
        $this->assertSame(50.0, $row->pct_low_reviews);
        $expected = ScanNicheJob::opportunityScore(
            $row->avg_gbp_score,
            $row->pct_no_website,
            $row->pct_low_reviews,
            $row->result_count,
        );
        $this->assertSame($expected, $row->opportunity_score);
        $this->assertNotNull($row->ran_at);
        $this->assertIsArray($row->sample_preview);
        $this->assertCount(2, $row->sample_preview);
        $this->assertSame('A', $row->sample_preview[0]['name']);
        $this->assertArrayHasKey('gbp_score', $row->sample_preview[0]);
        $this->assertTrue($row->sample_preview[0]['no_website']);
    }

    public function test_backfill_writes_sample_preview_to_requested_scan_date_row(): void
    {
        config(['services.google_places.key' => 'test-key']);

        $legacy = NicheScan::query()->create([
            'niche' => 'Dental Practice',
            'niche_query' => 'dental practice',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => '2026-05-27',
            'result_count' => 10,
            'sampled_count' => 1,
            'avg_gbp_score' => 50,
            'pct_no_website' => 100,
            'pct_low_reviews' => 100,
            'opportunity_score' => 70,
            'status' => 'complete',
            'ran_at' => now()->subDays(7),
            'sample_preview' => null,
        ]);

        NicheScan::query()->create([
            'niche' => 'Dental Practice',
            'niche_query' => 'dental practice',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => now()->toDateString(),
            'result_count' => 8,
            'sampled_count' => 1,
            'avg_gbp_score' => 40,
            'pct_no_website' => 50,
            'pct_low_reviews' => 50,
            'opportunity_score' => 60,
            'status' => 'complete',
            'ran_at' => now(),
            'sample_preview' => null,
        ]);

        Http::fake([
            'https://places.googleapis.com/v1/places:searchText' => Http::response([
                'places' => [['id' => 'places/a']],
            ], 200),
            'https://places.googleapis.com/v1/places/places/*' => Http::response([
                'id' => 'places/a',
                'displayName' => ['text' => 'Legacy Dental'],
                'userRatingCount' => 5,
                'photos' => [],
            ], 200),
        ]);

        $legacy->update(['status' => 'pending']);

        (new ScanNicheJob(
            niche: $legacy->niche,
            nicheQuery: $legacy->niche_query,
            city: $legacy->city,
            country: $legacy->country,
            sample: 1,
            scanDate: $legacy->scan_date->toDateString(),
        ))->handle(
            app(NicheSampleCollector::class),
            app(NicheExclusionService::class),
        );

        $legacy->refresh();
        $today = NicheScan::query()
            ->whereDate('scan_date', now()->toDateString())
            ->where('city', 'Leeds')
            ->first();

        $this->assertIsArray($legacy->sample_preview);
        $this->assertSame('Legacy Dental', $legacy->sample_preview[0]['name']);
        $this->assertNull($today->sample_preview);
        $this->assertSame(8, $today->result_count);
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
            app(NicheSampleCollector::class),
            app(NicheExclusionService::class),
        );

        $row = NicheScan::query()->first();

        $this->assertSame('complete', $row->status);
        $this->assertSame(0, $row->result_count);
        $this->assertSame(0, $row->sampled_count);
        $this->assertSame(0.0, $row->opportunity_score);
        $this->assertSame([], $row->sample_preview);
    }

    public function test_single_result_completes_with_opportunity_score_zero(): void
    {
        config(['services.google_places.key' => 'test-key']);

        Http::fake([
            'https://places.googleapis.com/v1/places:searchText' => Http::response([
                'places' => [['id' => 'places/a']],
            ], 200),
            'https://places.googleapis.com/v1/places/places/*' => Http::response([
                'id' => 'places/a',
                'displayName' => ['text' => 'A'],
                'userRatingCount' => 5,
                'photos' => [],
            ], 200),
        ]);

        (new ScanNicheJob(
            niche: 'Spark',
            nicheQuery: 'spark',
            city: 'Gloucester',
            country: 'GB',
            sample: 5,
            scanDate: '2026-05-28',
        ))->handle(
            app(NicheSampleCollector::class),
            app(NicheExclusionService::class),
        );

        $row = NicheScan::query()->first();

        $this->assertSame('complete', $row->status);
        $this->assertSame(1, $row->result_count);
        $this->assertSame(1, $row->sampled_count);
        $this->assertSame(0.0, $row->opportunity_score);

        $this->assertDatabaseHas('ignored_niches', [
            'niche' => 'Spark',
            'reason' => IgnoredNiche::REASON_LOW_RESULTS,
        ]);
    }
}
