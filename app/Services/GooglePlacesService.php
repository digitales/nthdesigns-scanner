<?php

namespace App\Services;

use App\Services\ApiUsage\ApiUsageGate;
use App\Services\GooglePlaces\PlacesDetailsClient;
use App\Services\GooglePlaces\PlacesNicheRankClient;
use App\Services\GooglePlaces\PlacesTextSearchClient;
use App\Services\GooglePlaces\PlacesWebsiteLookupClient;

class GooglePlacesService
{
    private string $apiKey;

    private string $baseUrl = 'https://places.googleapis.com/v1/places';

    public function __construct(
        private ApiUsageGate $usageGate,
        private PlacesDetailsClient $details,
        private PlacesNicheRankClient $nicheRank,
        private PlacesWebsiteLookupClient $websiteLookup,
    ) {
        $this->apiKey = config('services.google_places.key');
    }

    /**
     * @return list<string>
     */
    public function searchByNicheAndCity(string $niche, string $city, string $country = 'GB'): array
    {
        $query = "{$niche} in {$city}, {$country}";

        return (new PlacesTextSearchClient($this->apiKey, $this->usageGate, $this->baseUrl))
            ->searchPlaceIds($query, strtolower($country));
    }

    public function getPlaceDetails(string $placeId): ?array
    {
        return $this->details->get($placeId);
    }

    public function getTopRankedInNiche(
        string $niche,
        string $city,
        string $country = 'GB',
        ?string $excludePlaceId = null,
    ): ?array {
        return $this->nicheRank->getTopRanked($niche, $city, $country, $excludePlaceId);
    }

    public function findByWebsiteUrl(string $url): ?array
    {
        return $this->websiteLookup->findByWebsiteUrl($url);
    }
}
