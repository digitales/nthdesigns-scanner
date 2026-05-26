<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GooglePlacesService
{
    private string $apiKey;
    private string $baseUrl = 'https://places.googleapis.com/v1/places';

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
                'textQuery'   => $query,
                'maxResultCount' => 20,
                'regionCode'  => strtolower($country),
            ];

            if ($pageToken) {
                $payload['pageToken'] = $pageToken;
            }

            $response = Http::withHeaders([
                'Content-Type'       => 'application/json',
                'X-Goog-Api-Key'     => $this->apiKey,
                'X-Goog-FieldMask'   => 'places.id,nextPageToken',
            ])->post("{$this->baseUrl}:searchText", $payload);

            if ($response->failed()) {
                Log::error('GooglePlaces searchText failed', [
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                    'query'   => $query,
                ]);
                break;
            }

            $data = $response->json();

            foreach ($data['places'] ?? [] as $place) {
                if (!empty($place['id'])) {
                    $placeIds[] = $place['id'];
                }
            }

            $pageToken = $data['nextPageToken'] ?? null;

            if (!$pageToken) {
                break;
            }

            // Places API requires a short delay before using nextPageToken
            sleep(2);
        }

        return array_unique($placeIds);
    }

    /**
     * Fetch full place details for a single place_id.
     * Returns the raw API payload array.
     */
    public function getPlaceDetails(string $placeId): ?array
    {
        $fieldMask = implode(',', [
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
        ]);

        $response = Http::withHeaders([
            'Content-Type'     => 'application/json',
            'X-Goog-Api-Key'   => $this->apiKey,
            'X-Goog-FieldMask' => $fieldMask,
        ])->get("{$this->baseUrl}/{$placeId}");

        if ($response->failed()) {
            Log::error('GooglePlaces getPlaceDetails failed', [
                'status'   => $response->status(),
                'body'     => $response->body(),
                'place_id' => $placeId,
            ]);
            return null;
        }

        return $response->json();
    }

    /**
     * Fetch the top-ranked result for a niche+city query.
     * Used as the benchmark competitor in prospect reports.
     */
    public function getTopRankedInNiche(string $niche, string $city, string $country = 'GB'): ?array
    {
        $payload = [
            'textQuery'      => "{$niche} in {$city}, {$country}",
            'maxResultCount' => 1,
            'regionCode'     => strtolower($country),
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
            'Content-Type'     => 'application/json',
            'X-Goog-Api-Key'   => $this->apiKey,
            'X-Goog-FieldMask' => $fieldMask,
        ])->post("{$this->baseUrl}:searchText", $payload);

        if ($response->failed()) {
            return null;
        }

        return $response->json('places.0');
    }
}
