<?php

namespace App\Services\GoogleAds;

class CpcKeywordSeeder
{
    /**
     * @return list<string>
     */
    public function seeds(string $niche, string $city, string $country = 'GB'): array
    {
        $niche = trim($niche);
        $city = trim($city);
        $country = strtoupper(trim($country));

        if ($niche === '' || $city === '') {
            return [];
        }

        $candidates = [
            "{$niche} {$city}",
            "{$niche} in {$city}",
            "local {$niche} {$city}",
            "best {$niche} {$city}",
            "{$niche} near {$city}",
        ];

        if ($country === 'GB') {
            $candidates[] = "{$niche} {$city} uk";
        }

        $max = max(1, (int) config('google_ads.max_seed_keywords', 5));

        return array_values(array_unique(array_slice($candidates, 0, $max)));
    }
}
