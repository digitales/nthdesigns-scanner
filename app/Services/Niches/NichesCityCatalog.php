<?php

namespace App\Services\Niches;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class NichesCityCatalog
{
    private const ONS_QUERY_URL = 'https://services1.arcgis.com/ESMARspQHYMw9BZ9/arcgis/rest/services/Major_Towns_and_Cities_Dec_2015_Names_and_Codes_in_England_and_Wales_2022/FeatureServer/0/query';

    private const SUPPLEMENTARY_CITIES = [
        'Edinburgh', 'Glasgow', 'Aberdeen', 'Dundee', 'Inverness',
        'Cardiff', 'Swansea', 'Newport', 'Belfast', 'Derry',
    ];

    private const FALLBACK_CITIES = [
        'Birmingham', 'Manchester', 'Leeds', 'Sheffield', 'Bradford',
        'Liverpool', 'Bristol', 'Coventry', 'Leicester', 'Nottingham',
        'Newcastle', 'Southampton', 'Brighton', 'Plymouth', 'Stoke-on-Trent',
        'Wolverhampton', 'Derby', 'Swansea', 'Norwich', 'Luton',
        'Edinburgh', 'Glasgow', 'Aberdeen', 'Cardiff', 'Belfast',
        'London', 'Oxford', 'Cambridge', 'Bath', 'Exeter',
    ];

    /**
     * @param  callable(string): void|null  $warn
     * @return list<string>
     */
    public function fetchCities(?callable $warn = null): array
    {
        $response = Http::timeout(15)->get(self::ONS_QUERY_URL, [
            'where' => '1=1',
            'outFields' => 'TCITY15NM',
            'returnGeometry' => 'false',
            'resultRecordCount' => 2000,
            'f' => 'json',
        ]);

        if ($this->onsFetchFailed($response)) {
            if ($warn !== null) {
                $warn('ONS settlement fetch failed; using hardcoded city list.');
            }

            return $this->sortCities(self::FALLBACK_CITIES);
        }

        $englishCities = collect($response->json('features', []))
            ->map(fn (array $feature) => $feature['attributes']['TCITY15NM'] ?? null)
            ->filter()
            ->values();

        if ($englishCities->isEmpty()) {
            if ($warn !== null) {
                $warn('ONS response contained no settlements; using hardcoded city list.');
            }

            return $this->sortCities(self::FALLBACK_CITIES);
        }

        $names = $englishCities
            ->merge(self::SUPPLEMENTARY_CITIES)
            ->unique()
            ->values()
            ->all();

        return $this->sortCities($names);
    }

    private function onsFetchFailed(Response $response): bool
    {
        if ($response->failed()) {
            return true;
        }

        $body = $response->json();

        return is_array($body) && isset($body['error']);
    }

    /**
     * @param  list<string>  $cities
     * @return list<string>
     */
    private function sortCities(array $cities): array
    {
        $unique = array_values(array_unique($cities));
        usort($unique, fn (string $a, string $b) => strcasecmp($a, $b));

        return $unique;
    }
}
