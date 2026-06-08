<?php

namespace App\Services\GooglePlaces;

use App\Services\ApiUsage\ApiUsageGate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PlacesDetailsClient
{
    private const FIELD_MASK_VERSION = 'v1';

    private string $apiKey;

    private string $baseUrl = 'https://places.googleapis.com/v1/places';

    public function __construct(private ApiUsageGate $usageGate)
    {
        $this->apiKey = config('services.google_places.key');
    }

    public function get(string $placeId): ?array
    {
        $fieldMask = $this->fieldMask();
        $cacheKey = $this->cacheKey($placeId, $fieldMask);

        if ($this->cacheEnabled() && ! $this->cacheForce()) {
            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                return $cached;
            }
        }

        $this->usageGate->assertWithinQuota('google_places', 'place_details');

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Goog-Api-Key' => $this->apiKey,
            'X-Goog-FieldMask' => $fieldMask,
        ])->get("{$this->baseUrl}/{$placeId}");

        $this->usageGate->recordCompletedRequest('google_places', 'place_details');

        if ($response->failed()) {
            Log::error('GooglePlaces getPlaceDetails failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'place_id' => $placeId,
            ]);

            return null;
        }

        $payload = $response->json();

        if ($this->cacheEnabled() && is_array($payload)) {
            Cache::put(
                $cacheKey,
                $payload,
                now()->addDays(max(1, (int) config('scanner.places_details_ttl_days', 14))),
            );
        }

        return $payload;
    }

    private function fieldMask(): string
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

    private function cacheKey(string $placeId, string $fieldMask): string
    {
        return sprintf(
            'places:details:%s:%s:%s',
            self::FIELD_MASK_VERSION,
            $placeId,
            hash('sha256', $fieldMask),
        );
    }

    private function cacheEnabled(): bool
    {
        return (bool) config('scanner.places_cache_enabled', true);
    }

    private function cacheForce(): bool
    {
        return (bool) config('scanner.places_cache_force', false);
    }
}
