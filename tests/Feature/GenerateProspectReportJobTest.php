<?php

namespace Tests\Feature;

use App\Jobs\CaptureScreenshotJob;
use App\Jobs\GenerateProspectReportJob;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use App\Services\GooglePlacesService;
use App\Services\ReportBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GenerateProspectReportJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_search_benchmark_snapshot_instead_of_refetching_prospect(): void
    {
        config(['services.google_places.key' => 'test-key']);

        Http::fake([
            'https://places.googleapis.com/v1/places:searchText' => Http::response([
                'places' => [[
                    'id' => 'places/good-fabric',
                    'displayName' => ['text' => 'Good Fabric'],
                    'userRatingCount' => 19,
                    'photos' => array_fill(0, 10, []),
                    'rating' => 5.0,
                ]],
            ], 200),
        ]);

        $search = Search::factory()->create([
            'niche' => 'fabric shop',
            'city' => 'Wimbledon',
            'country' => 'GB',
            'benchmark_snapshot' => [
                'place_id' => 'places/top-listing',
                'name' => 'Wimbledon Fabrics',
                'review_count' => 97,
                'photo_count' => 42,
                'rating' => 4.9,
                'has_description' => true,
                'hours_complete' => true,
            ],
        ]);

        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'place_id' => 'places/good-fabric',
            'business_name' => 'Good Fabric',
            'review_count' => 19,
            'photo_count' => 10,
            'rating' => 5.0,
        ]);

        (new GenerateProspectReportJob($prospect))->handle(
            app(GooglePlacesService::class),
            app(ReportBuilderService::class),
        );

        $report = ProspectReport::where('prospect_id', $prospect->id)->firstOrFail();

        $this->assertSame('Wimbledon Fabrics', $report->report_data['benchmark']['name']);
        $this->assertSame(97, $report->report_data['benchmark']['review_count']);
        $this->assertSame('places/top-listing', $report->benchmark_place_id);
    }

    public function test_excludes_prospect_when_snapshot_matches_self(): void
    {
        config(['services.google_places.key' => 'test-key']);

        Http::fake([
            'https://places.googleapis.com/v1/places:searchText' => Http::response([
                'places' => [
                    [
                        'id' => 'places/good-fabric',
                        'displayName' => ['text' => 'Good Fabric'],
                        'userRatingCount' => 19,
                        'photos' => array_fill(0, 10, []),
                        'rating' => 5.0,
                    ],
                    [
                        'id' => 'places/top-listing',
                        'displayName' => ['text' => 'Wimbledon Fabrics'],
                        'userRatingCount' => 97,
                        'photos' => array_fill(0, 42, []),
                        'rating' => 4.9,
                        'editorialSummary' => ['text' => 'Local fabric shop'],
                        'regularOpeningHours' => ['periods' => [['open' => ['day' => 1]]]],
                    ],
                ],
            ], 200),
        ]);

        $search = Search::factory()->create([
            'niche' => 'fabric shop',
            'city' => 'Wimbledon',
            'country' => 'GB',
            'benchmark_snapshot' => [
                'place_id' => 'places/good-fabric',
                'name' => 'Good Fabric',
                'review_count' => 19,
                'photo_count' => 10,
                'rating' => 5.0,
                'has_description' => false,
                'hours_complete' => false,
            ],
        ]);

        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'place_id' => 'places/good-fabric',
            'business_name' => 'Good Fabric',
            'review_count' => 19,
            'photo_count' => 10,
            'rating' => 5.0,
        ]);

        (new GenerateProspectReportJob($prospect))->handle(
            app(GooglePlacesService::class),
            app(ReportBuilderService::class),
        );

        $report = ProspectReport::where('prospect_id', $prospect->id)->firstOrFail();

        $this->assertSame('Wimbledon Fabrics', $report->report_data['benchmark']['name']);
        $this->assertSame(97, $report->report_data['benchmark']['review_count']);
        $this->assertSame('places/top-listing', $report->benchmark_place_id);
    }

    public function test_direct_url_search_uses_enriched_benchmark_snapshot(): void
    {
        $search = Search::factory()->directUrl('https://example.com')->create([
            'niche' => 'dentist',
            'city' => 'Wimbledon',
            'country' => 'GB',
            'benchmark_snapshot' => [
                'place_id' => 'places/leader',
                'name' => 'Top Dentist',
                'review_count' => 90,
                'photo_count' => 15,
                'rating' => 4.8,
                'has_description' => true,
                'hours_complete' => true,
            ],
        ]);

        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'place_id' => 'places/prospect',
            'business_name' => 'Example Dental',
        ]);

        (new GenerateProspectReportJob($prospect))->handle(
            app(GooglePlacesService::class),
            app(ReportBuilderService::class),
        );

        $report = ProspectReport::where('prospect_id', $prospect->id)->firstOrFail();

        $this->assertSame('Top Dentist', $report->report_data['benchmark']['name']);
        $this->assertSame('dentist', $report->report_data['niche']);
        $this->assertSame('Wimbledon', $report->report_data['city']);
    }

    public function test_skips_screenshot_dispatch_when_desktop_already_stored(): void
    {
        Queue::fake();

        $search = Search::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'website_url' => 'https://example.com',
        ]);

        ProspectReport::factory()->create([
            'prospect_id' => $prospect->id,
            'screenshot_paths' => ['desktop' => 'https://cdn.example/reports/desktop.png'],
        ]);

        (new GenerateProspectReportJob($prospect))->handle(
            app(GooglePlacesService::class),
            app(ReportBuilderService::class),
        );

        Queue::assertNotPushed(CaptureScreenshotJob::class);
    }

    public function test_direct_url_search_builds_report_without_benchmark(): void
    {
        $search = Search::factory()->directUrl('https://example.com')->create([
            'benchmark_snapshot' => null,
        ]);

        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'place_id' => 'places/example',
            'business_name' => 'Example Ltd',
        ]);

        (new GenerateProspectReportJob($prospect))->handle(
            app(GooglePlacesService::class),
            app(ReportBuilderService::class),
        );

        $report = ProspectReport::where('prospect_id', $prospect->id)->firstOrFail();

        $this->assertNull($report->benchmark_place_id);
        $this->assertNull($report->report_data['benchmark']);
        $this->assertSame([], $report->report_data['comparison']);
    }
}
