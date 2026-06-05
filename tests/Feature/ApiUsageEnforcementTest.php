<?php

namespace Tests\Feature;

use App\Exceptions\ApiQuotaExceededException;
use App\Models\ApiUsageCounter;
use App\Services\BraveSearchService;
use App\Services\GooglePlacesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiUsageEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'scanner.api_quota.enforcement' => true,
            'scanner.api_quota.limits.google_places.text_search.daily' => 1,
            'scanner.api_quota.limits.google_places.text_search.monthly' => 100,
            'scanner.api_quota.limits.brave.web_search.daily' => 1,
            'scanner.api_quota.limits.brave.web_search.monthly' => 100,
            'services.google_places.key' => 'test-key',
            'services.brave_search.api_key' => 'test-token',
        ]);
    }

    public function test_google_places_blocks_when_daily_text_search_quota_exceeded(): void
    {
        ApiUsageCounter::query()->create([
            'provider' => 'google_places',
            'operation' => 'text_search',
            'period_type' => 'daily',
            'period_key' => now('Europe/London')->toDateString(),
            'count' => 1,
        ]);

        Http::fake();

        $this->expectException(ApiQuotaExceededException::class);

        app(GooglePlacesService::class)->getTopRankedInNiche('coffee', 'London');
    }

    public function test_brave_search_records_usage_on_success(): void
    {
        Http::fake([
            'https://api.search.brave.com/res/v1/web/search*' => Http::response([
                'web' => ['results' => []],
            ], 200),
        ]);

        app(BraveSearchService::class)->search('example query');

        $this->assertDatabaseHas('api_usage_counters', [
            'provider' => 'brave',
            'operation' => 'web_search',
            'period_type' => 'daily',
            'count' => 1,
        ]);
    }

    public function test_google_place_details_cache_hit_does_not_increment_usage(): void
    {
        config([
            'scanner.places_cache_enabled' => true,
            'scanner.api_quota.limits.google_places.place_details.daily' => 1,
        ]);

        $placeId = 'places/test-place';
        $cacheKey = sprintf(
            'places:details:v1:%s:%s',
            $placeId,
            hash('sha256', implode(',', [
                'id',
                'displayName',
                'formattedAddress',
                'nationalPhoneNumber',
                'websiteUri',
                'rating',
                'userRatingCount',
                'photos',
                'regularOpeningHours',
                'editorialSummary',
                'primaryType',
                'addressComponents',
                'businessStatus',
            ])),
        );

        cache()->put($cacheKey, ['id' => $placeId], now()->addDay());

        Http::fake();

        app(GooglePlacesService::class)->getPlaceDetails($placeId);

        Http::assertNothingSent();
        $this->assertDatabaseCount('api_usage_counters', 0);
    }
}
