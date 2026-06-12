<?php

namespace App\Services;

use App\Models\MarketCpcDefault;
use App\Models\User;
use App\Services\GoogleAds\CpcBenchmarkResult;
use App\Services\GoogleAds\GoogleAdsKeywordPlanService;

class MarketCpcLookupService
{
    public function __construct(
        private GoogleAdsKeywordPlanService $keywordPlan,
        private MarketCpcDefaultService $marketDefaults,
    ) {}

    public function isAvailable(): bool
    {
        return $this->keywordPlan->isAvailable();
    }

    public function savedDefault(User $user, string $niche, string $city, string $country = 'GB'): ?MarketCpcDefault
    {
        return $this->marketDefaults->find($user, $niche, $city, $country);
    }

    /**
     * Google Ads API only — no Places / search pipeline.
     */
    public function fetchFromGoogleAds(
        User $user,
        string $niche,
        string $city,
        string $country = 'GB',
    ): ?MarketCpcDefault {
        if (! $this->isAvailable()) {
            return null;
        }

        $result = $this->keywordPlan->lookupForMarket($niche, $city, $country);

        if ($result === null) {
            return null;
        }

        return $this->saveResult($user, $niche, $city, $country, $result);
    }

    public function saveResult(
        User $user,
        string $niche,
        string $city,
        string $country,
        CpcBenchmarkResult $result,
    ): MarketCpcDefault {
        return $this->marketDefaults->syncFromResult($user, $niche, $city, $country, $result);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function formatForForm(?MarketCpcDefault $default): ?array
    {
        return $this->marketDefaults->format($default);
    }
}
