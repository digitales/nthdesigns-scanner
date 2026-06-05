<?php

namespace Tests\Feature;

use App\Jobs\ScorePlaceJob;
use App\Jobs\ScrapeProspectsJob;
use App\Models\Search;
use App\Services\GooglePlacesService;
use App\Services\ProspectExclusionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScrapeProspectsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_benchmark_snapshot_when_places_returns_leader(): void
    {
        config(['services.google_places.key' => 'test-key']);

        Bus::fake([ScorePlaceJob::class]);

        Http::fake([
            'https://places.googleapis.com/v1/places:searchText' => Http::sequence()
                ->push(['places' => [['id' => 'places/prospect1']]], 200)
                ->push([
                    'places' => [[
                        'id' => 'places/leader',
                        'displayName' => ['text' => 'Leader Co'],
                        'userRatingCount' => 200,
                        'photos' => array_fill(0, 10, []),
                        'rating' => 4.8,
                        'editorialSummary' => ['text' => 'Best'],
                        'regularOpeningHours' => ['periods' => [['open' => ['day' => 1]]]],
                    ]],
                ], 200),
        ]);

        $search = Search::factory()->create(['status' => 'pending', 'total_found' => null]);

        (new ScrapeProspectsJob($search))->handle(
            app(GooglePlacesService::class),
            app(ProspectExclusionService::class),
        );

        $search->refresh();

        $this->assertSame('places/leader', $search->benchmark_snapshot['place_id']);
        $this->assertSame(200, $search->benchmark_snapshot['review_count']);
        $this->assertSame(1, $search->total_found);
        Bus::assertDispatched(ScorePlaceJob::class);
    }
}
