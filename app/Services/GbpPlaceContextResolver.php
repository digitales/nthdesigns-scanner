<?php

namespace App\Services;

/**
 * Derive search niche/city/country from a Places API place details payload.
 */
class GbpPlaceContextResolver
{
    /** @var list<string> */
    private const CITY_COMPONENT_TYPES = [
        'locality',
        'postal_town',
        'administrative_area_level_2',
        'sublocality',
        'sublocality_level_1',
    ];

    /**
     * @return array{niche: string|null, city: string|null, country: string|null}
     */
    public function resolve(array $payload, string $defaultCountry = 'GB'): array
    {
        return [
            'niche' => $this->nicheQuery($payload),
            'city' => $this->city($payload),
            'country' => $this->country($payload) ?? strtoupper($defaultCountry),
        ];
    }

    private function nicheQuery(array $payload): ?string
    {
        $primaryType = $payload['primaryType'] ?? null;

        if (! is_string($primaryType) || $primaryType === '') {
            return null;
        }

        $entry = collect(config('niches.niches', []))
            ->firstWhere('primary_type', $primaryType);

        if (is_array($entry) && filled($entry['query'] ?? null)) {
            return (string) $entry['query'];
        }

        return str_replace('_', ' ', $primaryType);
    }

    private function city(array $payload): ?string
    {
        $components = array_filter(
            $payload['addressComponents'] ?? [],
            fn ($component) => is_array($component),
        );

        foreach (self::CITY_COMPONENT_TYPES as $cityType) {
            foreach ($components as $component) {
                $types = $component['types'] ?? [];

                if (! is_array($types) || ! in_array($cityType, $types, true)) {
                    continue;
                }

                $name = $component['longText'] ?? $component['shortText'] ?? null;

                if (is_string($name) && $name !== '') {
                    return $name;
                }
            }
        }

        return null;
    }

    private function country(array $payload): ?string
    {
        foreach ($payload['addressComponents'] ?? [] as $component) {
            if (! is_array($component)) {
                continue;
            }

            $types = $component['types'] ?? [];

            if (! is_array($types) || ! in_array('country', $types, true)) {
                continue;
            }

            $code = $component['shortText'] ?? $component['longText'] ?? null;

            if (is_string($code) && strlen($code) === 2) {
                return strtoupper($code);
            }
        }

        return null;
    }
}
