<?php

namespace App\Console\Commands;

use App\Services\GoogleAds\CpcKeywordSeeder;
use App\Services\GoogleAds\GoogleAdsClient;
use App\Services\GoogleAds\GoogleAdsGeoTargetResolver;
use App\Services\GoogleAds\GoogleAdsKeywordPlanService;
use Illuminate\Console\Command;

class GoogleAdsCpcLookupCommand extends Command
{
    protected $signature = 'google-ads:cpc
                            {niche : Business niche, e.g. "dental practice"}
                            {city : City or town, e.g. Birmingham}
                            {--country=GB : ISO country code}';

    protected $description = 'Look up a median CPC benchmark from Google Ads Keyword Planner API';

    public function handle(
        GoogleAdsClient $client,
        GoogleAdsGeoTargetResolver $geoTargets,
        CpcKeywordSeeder $keywordSeeder,
        GoogleAdsKeywordPlanService $keywordPlan,
    ): int {
        if (! config('google_ads.enabled')) {
            $this->error('GOOGLE_ADS_ENABLED is false. Set it to true after configuring credentials.');

            return self::FAILURE;
        }

        if (! $client->isConfigured()) {
            $this->error('Google Ads API is not fully configured. See docs/integrations/google-ads-cpc.md');

            return self::FAILURE;
        }

        $niche = (string) $this->argument('niche');
        $city = (string) $this->argument('city');
        $country = strtoupper((string) $this->option('country'));

        $keywords = $keywordSeeder->seeds($niche, $city, $country);
        $geo = $geoTargets->resolve($city, $country);

        $this->table(
            ['Input', 'Value'],
            [
                ['Niche', $niche],
                ['City', $city],
                ['Country', $country],
                ['Seed keywords', implode(', ', $keywords)],
                ['Geo target', $geo ?? '(unresolved)'],
                ['Customer ID', config('google_ads.customer_id')],
            ],
        );

        $this->newLine();
        $this->line('Calling Google Ads generateKeywordIdeas…');

        $benchmark = $keywordPlan->lookupForMarket($niche, $city, $country);

        if ($benchmark === null) {
            $this->warn('No CPC benchmark could be resolved for this market.');

            return self::FAILURE;
        }

        $this->info(sprintf('Median CPC benchmark: £%.2f per click', $benchmark->benchmark));
        $this->line('Seed keywords: '.implode(', ', $benchmark->keywords));

        if ($benchmark->geoTarget) {
            $this->line('Geo target: '.$benchmark->geoTarget);
        }

        return self::SUCCESS;
    }
}
