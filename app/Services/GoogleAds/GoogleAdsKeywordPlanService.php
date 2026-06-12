<?php

namespace App\Services\GoogleAds;

use App\Models\Search;
use Illuminate\Support\Facades\Log;

class GoogleAdsKeywordPlanService
{
    public function __construct(
        private GoogleAdsClient $client,
        private GoogleAdsGeoTargetResolver $geoTargets,
        private CpcKeywordSeeder $keywordSeeder,
    ) {}

    public function isAvailable(): bool
    {
        return config('google_ads.enabled') && $this->client->isConfigured();
    }

    public function lookupForSearch(Search $search): ?CpcBenchmarkResult
    {
        if ($search->niche === null || $search->city === null) {
            return null;
        }

        return $this->lookupForMarket(
            $search->niche,
            $search->city,
            $search->country ?? 'GB',
        );
    }

    public function lookupForMarket(string $niche, string $city, string $country = 'GB'): ?CpcBenchmarkResult
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $keywords = $this->keywordSeeder->seeds($niche, $city, $country);

        if ($keywords === []) {
            return null;
        }

        $geoTarget = $this->geoTargets->resolve($city, $country);

        if ($geoTarget === null) {
            Log::warning('google_ads.cpc_skipped_no_geo', [
                'niche' => $niche,
                'city' => $city,
                'country' => $country,
            ]);

            return null;
        }

        try {
            $response = $this->client->post(
                'customers/'.config('google_ads.customer_id').':generateKeywordIdeas',
                [
                    'language' => config('google_ads.language_constant'),
                    'geoTargetConstants' => [$geoTarget],
                    'keywordPlanNetwork' => config('google_ads.keyword_plan_network'),
                    'keywordSeed' => [
                        'keywords' => $keywords,
                    ],
                    'pageSize' => config('google_ads.page_size'),
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('google_ads.cpc_lookup_failed', [
                'niche' => $niche,
                'city' => $city,
                'country' => $country,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $cpcValues = $this->extractCpcMicros($response['results'] ?? []);

        if ($cpcValues === []) {
            return null;
        }

        sort($cpcValues);
        $medianMicros = $cpcValues[(int) floor((count($cpcValues) - 1) / 2)];

        return new CpcBenchmarkResult(
            benchmark: round($medianMicros / 1_000_000, 2),
            keywords: $keywords,
            geoTarget: $geoTarget,
        );
    }

    /**
     * @param  list<mixed>  $results
     * @return list<int>
     */
    private function extractCpcMicros(array $results): array
    {
        $values = [];

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            $metrics = $result['keywordIdeaMetrics'] ?? null;

            if (! is_array($metrics)) {
                continue;
            }

            $micros = $this->firstPositiveInt(
                $metrics['averageCpcMicros'] ?? null,
                $metrics['highTopOfPageBidMicros'] ?? null,
                $metrics['lowTopOfPageBidMicros'] ?? null,
            );

            if ($micros !== null) {
                $values[] = $micros;
            }
        }

        return $values;
    }

    private function firstPositiveInt(mixed ...$candidates): ?int
    {
        foreach ($candidates as $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $int = (int) $value;

            if ($int > 0) {
                return $int;
            }
        }

        return null;
    }
}
