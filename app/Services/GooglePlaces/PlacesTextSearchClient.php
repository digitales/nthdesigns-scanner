<?php

namespace App\Services\GooglePlaces;

use App\Services\ApiUsage\ApiUsageGate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PlacesTextSearchClient
{
    public function __construct(
        private string $apiKey,
        private ApiUsageGate $usageGate,
        private string $baseUrl = 'https://places.googleapis.com/v1/places',
    ) {}

    /**
     * @return list<string>
     */
    public function searchPlaceIds(string $textQuery, string $regionCode = 'gb', int $maxPages = 3): array
    {
        $placeIds = [];
        $pageToken = null;

        for ($page = 0; $page < $maxPages; $page++) {
            $payload = [
                'textQuery' => $textQuery,
                'maxResultCount' => 20,
                'regionCode' => strtolower($regionCode),
            ];

            if ($pageToken) {
                $payload['pageToken'] = $pageToken;
            }

            $this->usageGate->assertWithinQuota('google_places', 'text_search');

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Goog-Api-Key' => $this->apiKey,
                'X-Goog-FieldMask' => 'places.id,nextPageToken',
            ])->post("{$this->baseUrl}:searchText", $payload);

            $this->usageGate->recordCompletedRequest('google_places', 'text_search');

            if ($response->failed()) {
                Log::error('GooglePlaces searchText failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'query' => $textQuery,
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
}
