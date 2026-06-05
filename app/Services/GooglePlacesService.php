<?php

namespace App\Services;

use App\Support\WebsiteUrlNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GooglePlacesService
{
    private string $apiKey;

    private string $baseUrl = 'https://places.googleapis.com/v1/places';

    private const DETAILS_FIELD_MASK_VERSION = 'v1';

    public function __construct()
    {
        $this->apiKey = config('services.google_places.key');
    }

    /**
     * Search for businesses by niche and city.
     * Returns an array of place_id strings (up to 60 via pagination).
     */
    public function searchByNicheAndCity(string $niche, string $city, string $country = 'GB'): array
    {
        $query = "{$niche} in {$city}, {$country}";
        $placeIds = [];
        $pageToken = null;

        for ($page = 0; $page < 3; $page++) {
            $payload = [
                'textQuery' => $query,
                'maxResultCount' => 20,
                'regionCode' => strtolower($country),
            ];

            if ($pageToken) {
                $payload['pageToken'] = $pageToken;
            }

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Goog-Api-Key' => $this->apiKey,
                'X-Goog-FieldMask' => 'places.id,nextPageToken',
            ])->post("{$this->baseUrl}:searchText", $payload);

            if ($response->failed()) {
                Log::error('GooglePlaces searchText failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'query' => $query,
                ]);
                break;
            }

            $data = $response->json();

            foreach ($data['places'] ?? [] as $place) {
                if (! empty($place['id'])) {
                    $placeIds[] = $place['id'];
                }
            }

            $pageToken = $data['nextPageToken'] ?? null;

            if (! $pageToken) {
                break;
            }

            $delay = (int) config('scanner.places_pagination_delay_seconds', 2);
            if ($delay > 0) {
                sleep($delay);
            }
        }

        return array_unique($placeIds);
    }

    /**
     * Fetch full place details for a single place_id.
     * Returns the raw API payload array.
     */
    public function getPlaceDetails(string $placeId): ?array
    {
        $fieldMask = $this->detailsFieldMask();
        $cacheKey = $this->detailsCacheKey($placeId, $fieldMask);

        if ($this->placesCacheEnabled() && ! $this->placesCacheForce()) {
            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                return $cached;
            }
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Goog-Api-Key' => $this->apiKey,
            'X-Goog-FieldMask' => $fieldMask,
        ])->get("{$this->baseUrl}/{$placeId}");

        if ($response->failed()) {
            Log::error('GooglePlaces getPlaceDetails failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'place_id' => $placeId,
            ]);

            return null;
        }

        $payload = $response->json();

        if ($this->placesCacheEnabled() && is_array($payload)) {
            Cache::put(
                $cacheKey,
                $payload,
                now()->addDays(max(1, (int) config('scanner.places_details_ttl_days', 14))),
            );
        }

        return $payload;
    }

    /**
     * Fetch the top-ranked result for a niche+city query.
     * Used as the benchmark competitor in prospect reports.
     *
     * @param  string|null  $excludePlaceId  Skip this place (e.g. the prospect being reported on).
     */
    public function getTopRankedInNiche(
        string $niche,
        string $city,
        string $country = 'GB',
        ?string $excludePlaceId = null,
    ): ?array {
        $payload = [
            'textQuery' => "{$niche} in {$city}, {$country}",
            'maxResultCount' => 10,
            'regionCode' => strtolower($country),
        ];

        $fieldMask = implode(',', [
            'places.id',
            'places.displayName',
            'places.rating',
            'places.userRatingCount',
            'places.photos',
            'places.regularOpeningHours',
            'places.editorialSummary',
        ]);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Goog-Api-Key' => $this->apiKey,
            'X-Goog-FieldMask' => $fieldMask,
        ])->post("{$this->baseUrl}:searchText", $payload);

        if ($response->failed()) {
            return null;
        }

        foreach ($response->json('places') ?? [] as $place) {
            if ($excludePlaceId !== null && ($place['id'] ?? null) === $excludePlaceId) {
                continue;
            }

            return $place;
        }

        return null;
    }

    public function findByWebsiteUrl(string $url): ?array
    {
        $normalizer = app(WebsiteUrlNormalizer::class);
        $targetHost = $normalizer->host($url);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Goog-Api-Key' => $this->apiKey,
            'X-Goog-FieldMask' => 'places.id,places.websiteUri,places.displayName',
        ])->post("{$this->baseUrl}:searchText", [
            'textQuery' => $targetHost,
            'maxResultCount' => 20,
        ]);

        if ($response->failed()) {
            Log::warning('GooglePlaces findByWebsiteUrl searchText failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'host' => $targetHost,
            ]);

            return null;
        }

        foreach ($response->json('places') ?? [] as $place) {
            $websiteUri = $place['websiteUri'] ?? null;
            $placeId = $place['id'] ?? null;

            if (! $websiteUri || ! $placeId) {
                continue;
            }

            try {
                $placeHost = $normalizer->host($websiteUri);
            } catch (\InvalidArgumentException) {
                continue;
            }

            if ($placeHost === $targetHost) {
                return $this->getPlaceDetails($placeId);
            }
        }

        return null;
    }

    private function detailsFieldMask(): string
    {
        return implode(',', [
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
        ]);
    }

    private function detailsCacheKey(string $placeId, string $fieldMask): string
    {
        return sprintf(
            'places:details:%s:%s:%s',
            self::DETAILS_FIELD_MASK_VERSION,
            $placeId,
            hash('sha256', $fieldMask),
        );
    }

    private function placesCacheEnabled(): bool
    {
        return (bool) config('scanner.places_cache_enabled', true);
    }

    private function placesCacheForce(): bool
    {
        return (bool) config('scanner.places_cache_force', false);
    }
}
