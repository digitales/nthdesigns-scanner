<?php

namespace App\Services\GooglePlaces;

use App\Services\ApiUsage\ApiUsageGate;
use App\Support\WebsiteUrlNormalizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PlacesWebsiteLookupClient
{
    private string $apiKey;

    private string $baseUrl = 'https://places.googleapis.com/v1/places';

    public function __construct(
        private ApiUsageGate $usageGate,
        private PlacesDetailsClient $details,
    ) {
        $this->apiKey = config('services.google_places.key');
    }

    public function findByWebsiteUrl(string $url): ?array
    {
        $normalizer = app(WebsiteUrlNormalizer::class);
        $targetHost = $normalizer->host($url);

        $this->usageGate->assertWithinQuota('google_places', 'text_search');

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Goog-Api-Key' => $this->apiKey,
            'X-Goog-FieldMask' => 'places.id,places.websiteUri,places.displayName',
        ])->post("{$this->baseUrl}:searchText", [
            'textQuery' => $targetHost,
            'maxResultCount' => 20,
        ]);

        $this->usageGate->recordCompletedRequest('google_places', 'text_search');

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
                return $this->details->get($placeId);
            }
        }

        return null;
    }
}
