<?php

namespace App\Services\GooglePlaces;

use App\Services\ApiUsage\ApiUsageGate;
use Illuminate\Support\Facades\Http;

class PlacesNicheRankClient
{
    private string $apiKey;

    private string $baseUrl = 'https://places.googleapis.com/v1/places';

    public function __construct(private ApiUsageGate $usageGate)
    {
        $this->apiKey = config('services.google_places.key');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getTopRanked(
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

        $this->usageGate->assertWithinQuota('google_places', 'text_search');

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Goog-Api-Key' => $this->apiKey,
            'X-Goog-FieldMask' => $fieldMask,
        ])->post("{$this->baseUrl}:searchText", $payload);

        $this->usageGate->recordCompletedRequest('google_places', 'text_search');

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
}
