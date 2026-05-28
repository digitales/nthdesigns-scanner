<?php

namespace Tests\Feature;

use App\Jobs\GenerateProspectReportJob;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use App\Services\GooglePlacesService;
use App\Services\ReportBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
                    'id'              => 'places/good-fabric',
                    'displayName'     => ['text' => 'Good Fabric'],
                    'userRatingCount' => 19,
                    'photos'          => array_fill(0, 10, []),
                    'rating'          => 5.0,
                ]],
            ], 200),
        ]);

        $search = Search::factory()->create([
            'niche'   => 'fabric shop',
            'city'    => 'Wimbledon',
            'country' => 'GB',
            'benchmark_snapshot' => [
                'place_id'        => 'places/top-listing',
                'name'            => 'Wimbledon Fabrics',
                'review_count'    => 97,
                'photo_count'     => 42,
                'rating'          => 4.9,
                'has_description' => true,
                'hours_complete'  => true,
            ],
        ]);

        $prospect = Prospect::factory()->create([
            'search_id'     => $search->id,
            'place_id'      => 'places/good-fabric',
            'business_name' => 'Good Fabric',
            'review_count'  => 19,
            'photo_count'   => 10,
            'rating'        => 5.0,
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
                        'id'              => 'places/good-fabric',
                        'displayName'     => ['text' => 'Good Fabric'],
                        'userRatingCount' => 19,
                        'photos'          => array_fill(0, 10, []),
                        'rating'          => 5.0,
                    ],
                    [
                        'id'              => 'places/top-listing',
                        'displayName'     => ['text' => 'Wimbledon Fabrics'],
                        'userRatingCount' => 97,
                        'photos'          => array_fill(0, 42, []),
                        'rating'          => 4.9,
                        'editorialSummary' => ['text' => 'Local fabric shop'],
                        'regularOpeningHours' => ['periods' => [['open' => ['day' => 1]]]],
                    ],
                ],
            ], 200),
        ]);

        $search = Search::factory()->create([
            'niche'   => 'fabric shop',
            'city'    => 'Wimbledon',
            'country' => 'GB',
            'benchmark_snapshot' => [
                'place_id'        => 'places/good-fabric',
                'name'            => 'Good Fabric',
                'review_count'    => 19,
                'photo_count'     => 10,
                'rating'          => 5.0,
                'has_description' => false,
                'hours_complete'  => false,
            ],
        ]);

        $prospect = Prospect::factory()->create([
            'search_id'     => $search->id,
            'place_id'      => 'places/good-fabric',
            'business_name' => 'Good Fabric',
            'review_count'  => 19,
            'photo_count'   => 10,
            'rating'        => 5.0,
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
}
