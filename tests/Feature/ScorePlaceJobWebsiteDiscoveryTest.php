<?php

namespace Tests\Feature;

use App\Jobs\AuditSiteJob;
use App\Jobs\ScorePlaceJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Services\GbpScoringService;
use App\Services\GooglePlacesService;
use App\Services\SearchStatusService;
use App\Services\WebsiteDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScorePlaceJobWebsiteDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google_places.key' => 'test-places',
            'services.brave_search.api_key' => 'test-brave',
            'scanner.website_discovery_enabled' => true,
            'scanner.website_discovery_provider' => 'brave',
        ]);
    }

    public function test_discovers_website_and_dispatches_audit(): void
    {
        Bus::fake([AuditSiteJob::class]);

        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'scan_type' => 'combined',
            'city' => 'Manchester',
            'niche' => 'solicitor',
        ]);

        $placePayload = [
            'id' => 'places/abc',
            'displayName' => ['text' => 'Briar & Wren Solicitors Ltd'],
            'formattedAddress' => '1 High St, Manchester',
            'userRatingCount' => 12,
            'photos' => [],
        ];

        $this->mock(GooglePlacesService::class, function ($mock) use ($placePayload) {
            $mock->shouldIgnoreMissing();
            $mock->shouldReceive('getPlaceDetails')
                ->once()
                ->with('places/abc')
                ->andReturn($placePayload);
        });

        Http::fake([
            'https://api.search.brave.com/res/v1/web/search*' => Http::response([
                'web' => [
                    'results' => [
                        [
                            'url' => 'https://briarwren.co.uk',
                            'title' => 'Briar & Wren Solicitors — Manchester',
                            'description' => 'Manchester solicitors',
                        ],
                    ],
                ],
            ], 200),
        ]);

        (new ScorePlaceJob($search, 'places/abc'))->handle(
            app(GooglePlacesService::class),
            app(GbpScoringService::class),
            app(SearchStatusService::class),
            app(WebsiteDiscoveryService::class),
        );

        $prospect = Prospect::where('place_id', 'places/abc')->first();

        $this->assertNotNull($prospect);
        $this->assertSame('https://briarwren.co.uk', $prospect->website_url);
        $this->assertSame('brave', $prospect->website_url_source);
        $this->assertSame('high', $prospect->website_discovery_confidence);
        $this->assertNotNull($prospect->website_discovered_at);
        $this->assertContains(WebsiteDiscoveryService::GBP_FLAG_NOT_ON_PROFILE, $prospect->gbp_flags);
        $this->assertNotContains('No website listed', $prospect->gbp_flags);

        Bus::assertDispatched(AuditSiteJob::class);
    }

    public function test_discovers_website_via_google_cse_provider(): void
    {
        config([
            'scanner.website_discovery_provider' => 'google_cse',
            'services.google_cse.key' => 'test-key',
            'services.google_cse.cx' => 'test-cx',
        ]);

        Bus::fake([AuditSiteJob::class]);

        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'scan_type' => 'combined',
            'city' => 'Manchester',
            'niche' => 'solicitor',
        ]);

        $placePayload = [
            'id' => 'places/abc',
            'displayName' => ['text' => 'Briar & Wren Solicitors Ltd'],
            'formattedAddress' => '1 High St, Manchester',
            'userRatingCount' => 12,
            'photos' => [],
        ];

        $this->mock(GooglePlacesService::class, function ($mock) use ($placePayload) {
            $mock->shouldIgnoreMissing();
            $mock->shouldReceive('getPlaceDetails')
                ->once()
                ->with('places/abc')
                ->andReturn($placePayload);
        });

        Http::fake([
            'https://www.googleapis.com/customsearch/v1*' => Http::response([
                'items' => [
                    [
                        'link' => 'https://briarwren.co.uk',
                        'title' => 'Briar & Wren Solicitors — Manchester',
                        'snippet' => 'Manchester solicitors',
                    ],
                ],
            ], 200),
        ]);

        (new ScorePlaceJob($search, 'places/abc'))->handle(
            app(GooglePlacesService::class),
            app(GbpScoringService::class),
            app(SearchStatusService::class),
            app(WebsiteDiscoveryService::class),
        );

        $prospect = Prospect::where('place_id', 'places/abc')->first();

        $this->assertSame('google_cse', $prospect->website_url_source);
    }

    public function test_skips_cse_when_gbp_has_website(): void
    {
        Bus::fake([AuditSiteJob::class]);

        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'scan_type' => 'combined',
            'city' => 'Manchester',
        ]);

        $placePayload = [
            'id' => 'places/abc',
            'displayName' => ['text' => 'Example Ltd'],
            'websiteUri' => 'https://example.com',
            'userRatingCount' => 12,
            'photos' => [],
        ];

        $this->mock(GooglePlacesService::class, function ($mock) use ($placePayload) {
            $mock->shouldIgnoreMissing();
            $mock->shouldReceive('getPlaceDetails')
                ->once()
                ->andReturn($placePayload);
        });

        Http::fake();

        (new ScorePlaceJob($search, 'places/abc'))->handle(
            app(GooglePlacesService::class),
            app(GbpScoringService::class),
            app(SearchStatusService::class),
            app(WebsiteDiscoveryService::class),
        );

        Http::assertNothingSent();

        $prospect = Prospect::where('place_id', 'places/abc')->first();
        $this->assertSame('gbp', $prospect->website_url_source);
        $this->assertSame('https://example.com', $prospect->website_url);
        Bus::assertDispatched(AuditSiteJob::class);
    }

    public function test_skips_when_prospect_already_exists(): void
    {
        Bus::fake([AuditSiteJob::class]);

        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'scan_type' => 'combined',
            'city' => 'Manchester',
        ]);

        Prospect::factory()->create([
            'search_id' => $search->id,
            'place_id' => 'places/abc',
            'audit_status' => 'pending',
        ]);

        $this->mock(GooglePlacesService::class, function ($mock) {
            $mock->shouldNotReceive('getPlaceDetails');
        });

        (new ScorePlaceJob($search, 'places/abc'))->handle(
            app(GooglePlacesService::class),
            app(GbpScoringService::class),
            app(SearchStatusService::class),
            app(WebsiteDiscoveryService::class),
        );

        $this->assertSame(1, Prospect::where('search_id', $search->id)->count());
        Bus::assertNotDispatched(AuditSiteJob::class);
    }

    public function test_cse_failure_leaves_prospect_without_website(): void
    {
        Bus::fake([AuditSiteJob::class]);

        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'scan_type' => 'combined',
            'city' => 'Manchester',
        ]);

        $placePayload = [
            'id' => 'places/abc',
            'displayName' => ['text' => 'No Site Co'],
            'userRatingCount' => 5,
            'photos' => [],
        ];

        $this->mock(GooglePlacesService::class, function ($mock) use ($placePayload) {
            $mock->shouldIgnoreMissing();
            $mock->shouldReceive('getPlaceDetails')->once()->andReturn($placePayload);
        });

        Http::fake([
            'https://api.search.brave.com/res/v1/web/search*' => Http::response([], 500),
        ]);

        (new ScorePlaceJob($search, 'places/abc'))->handle(
            app(GooglePlacesService::class),
            app(GbpScoringService::class),
            app(SearchStatusService::class),
            app(WebsiteDiscoveryService::class),
        );

        $prospect = Prospect::where('place_id', 'places/abc')->first();
        $this->assertNull($prospect->website_url);
        $this->assertContains('No website listed', $prospect->gbp_flags);
        Bus::assertNotDispatched(AuditSiteJob::class);
    }
}
