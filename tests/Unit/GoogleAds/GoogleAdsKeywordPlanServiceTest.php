<?php

namespace Tests\Unit\GoogleAds;

use App\Services\GoogleAds\CpcKeywordSeeder;
use App\Services\GoogleAds\GoogleAdsClient;
use App\Services\GoogleAds\GoogleAdsGeoTargetResolver;
use App\Services\GoogleAds\GoogleAdsKeywordPlanService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAdsKeywordPlanServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('google_ads.enabled', true);
        Config::set('google_ads.developer_token', 'dev-token');
        Config::set('google_ads.customer_id', '1234567890');
        Config::set('google_ads.oauth.client_id', 'client');
        Config::set('google_ads.oauth.client_secret', 'secret');
        Config::set('google_ads.oauth.refresh_token', 'refresh');
        Config::set('google_ads.geo_targets.birmingham|GB', 'geoTargetConstants/9041139');
    }

    public function test_returns_median_cpc_from_keyword_ideas(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token', 'expires_in' => 3600]),
            'googleads.googleapis.com/*' => Http::response([
                'results' => [
                    [
                        'text' => 'dental practice birmingham',
                        'keywordIdeaMetrics' => ['averageCpcMicros' => '6000000'],
                    ],
                    [
                        'text' => 'dentist birmingham',
                        'keywordIdeaMetrics' => ['averageCpcMicros' => '10000000'],
                    ],
                    [
                        'text' => 'private dentist birmingham',
                        'keywordIdeaMetrics' => ['highTopOfPageBidMicros' => '8000000'],
                    ],
                ],
            ]),
        ]);

        $benchmark = app(GoogleAdsKeywordPlanService::class)->lookupForMarket(
            'dental practice',
            'Birmingham',
            'GB',
        );

        $this->assertSame(8.0, $benchmark?->benchmark);
    }

    public function test_returns_null_when_not_configured(): void
    {
        Config::set('google_ads.enabled', false);

        $benchmark = app(GoogleAdsKeywordPlanService::class)->lookupForMarket(
            'dental practice',
            'Birmingham',
            'GB',
        );

        $this->assertNull($benchmark);
    }

    public function test_geo_resolver_uses_configured_target_without_api_call(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token', 'expires_in' => 3600]),
        ]);

        $geo = app(GoogleAdsGeoTargetResolver::class)->resolve('Birmingham', 'GB');

        $this->assertSame('geoTargetConstants/9041139', $geo);
        Http::assertNothingSent();
    }
}
