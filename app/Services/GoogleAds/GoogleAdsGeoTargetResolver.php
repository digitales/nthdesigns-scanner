<?php

namespace App\Services\GoogleAds;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAdsGeoTargetResolver
{
    public function __construct(
        private GoogleAdsClient $client,
    ) {}

    public function resolve(string $city, string $country = 'GB'): ?string
    {
        $city = trim($city);
        $country = strtoupper(trim($country));

        if ($city === '') {
            return null;
        }

        $key = Str::lower($city).'|'.$country;
        $configured = config("google_ads.geo_targets.{$key}");

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        if (! $this->client->isConfigured()) {
            return null;
        }

        try {
            $response = $this->client->post('geoTargetConstants:suggestGeoTargetConstants', [
                'locale' => 'en',
                'countryCode' => $country,
                'locationNames' => [
                    'names' => [$this->locationQuery($city, $country)],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('google_ads.geo_target_failed', [
                'city' => $city,
                'country' => $country,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $suggestions = $response['geoTargetConstantSuggestions'] ?? [];

        if (! is_array($suggestions) || $suggestions === []) {
            return null;
        }

        $first = $suggestions[0];
        $resource = $first['geoTargetConstant']['resourceName'] ?? null;

        return is_string($resource) && $resource !== '' ? $resource : null;
    }

    private function locationQuery(string $city, string $country): string
    {
        return match ($country) {
            'GB' => "{$city}, United Kingdom",
            'IE' => "{$city}, Ireland",
            'US' => "{$city}, United States",
            default => "{$city}, {$country}",
        };
    }
}
