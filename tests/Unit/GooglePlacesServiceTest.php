<?php

namespace Tests\Unit;

use App\Services\GooglePlacesService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GooglePlacesServiceTest extends TestCase
{
    public function test_get_top_ranked_excludes_prospect_place_id(): void
    {
        config(['services.google_places.key' => 'test-key']);

        Http::fake([
            'https://places.googleapis.com/v1/places:searchText' => Http::response([
                'places' => [
                    [
                        'id'          => 'places/self',
                        'displayName' => ['text' => 'Good Fabric'],
                    ],
                    [
                        'id'          => 'places/competitor',
                        'displayName' => ['text' => 'Wimbledon Fabrics'],
                    ],
                ],
            ], 200),
        ]);

        $result = app(GooglePlacesService::class)->getTopRankedInNiche(
            'fabric shop',
            'Wimbledon',
            'GB',
            'places/self',
        );

        $this->assertSame('places/competitor', $result['id']);
        $this->assertSame('Wimbledon Fabrics', $result['displayName']['text']);
    }

    public function test_find_by_website_url_returns_details_on_host_match(): void
    {
        config(['services.google_places.key' => 'test-key']);

        Http::fake([
            'https://places.googleapis.com/v1/places:searchText' => Http::response([
                'places' => [
                    [
                        'id'          => 'places/abc',
                        'websiteUri'  => 'https://www.example.com',
                        'displayName' => ['text' => 'Example Ltd'],
                    ],
                ],
            ], 200),
            'https://places.googleapis.com/v1/places/places/abc' => Http::response([
                'id'              => 'places/abc',
                'displayName'     => ['text' => 'Example Ltd'],
                'websiteUri'      => 'https://example.com',
                'userRatingCount' => 10,
                'photos'          => [],
            ], 200),
        ]);

        $result = app(GooglePlacesService::class)->findByWebsiteUrl('https://example.com');

        $this->assertNotNull($result);
        $this->assertSame('places/abc', $result['id']);
    }

    public function test_find_by_website_url_returns_null_when_no_host_match(): void
    {
        config(['services.google_places.key' => 'test-key']);

        Http::fake([
            'https://places.googleapis.com/v1/places:searchText' => Http::response([
                'places' => [
                    [
                        'id'         => 'places/other',
                        'websiteUri' => 'https://other.com',
                    ],
                ],
            ], 200),
        ]);

        $result = app(GooglePlacesService::class)->findByWebsiteUrl('https://example.com');

        $this->assertNull($result);
    }

    public function test_find_by_website_url_returns_null_on_api_failure(): void
    {
        config(['services.google_places.key' => 'test-key']);

        Http::fake([
            'https://places.googleapis.com/v1/places:searchText' => Http::response([], 500),
        ]);

        $result = app(GooglePlacesService::class)->findByWebsiteUrl('https://example.com');

        $this->assertNull($result);
    }

    public function test_get_place_details_uses_cache_on_second_request(): void
    {
        config([
            'services.google_places.key' => 'test-key',
            'scanner.places_cache_enabled' => true,
            'scanner.places_cache_force' => false,
        ]);

        Http::fake([
            'https://places.googleapis.com/v1/places/places/abc' => Http::response([
                'id'              => 'places/abc',
                'displayName'     => ['text' => 'Example Ltd'],
                'websiteUri'      => 'https://example.com',
                'userRatingCount' => 10,
                'photos'          => [],
            ], 200),
        ]);

        $service = app(GooglePlacesService::class);

        $first = $service->getPlaceDetails('places/abc');
        $second = $service->getPlaceDetails('places/abc');

        $this->assertSame('places/abc', $first['id']);
        $this->assertSame($first, $second);
        Http::assertSentCount(1);
    }
}
