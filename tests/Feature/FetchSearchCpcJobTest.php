<?php

namespace Tests\Feature;

use App\Jobs\FetchSearchCpcJob;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchSearchCpcJobTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_persists_cpc_on_search(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token', 'expires_in' => 3600]),
            'googleads.googleapis.com/*' => Http::response([
                'results' => [
                    ['keywordIdeaMetrics' => ['averageCpcMicros' => '7500000']],
                ],
            ]),
        ]);

        $search = Search::factory()->create([
            'user_id' => User::factory()->create()->id,
            'niche' => 'dental practice',
            'city' => 'Birmingham',
            'country' => 'GB',
        ]);

        (new FetchSearchCpcJob($search))->handle(app(\App\Services\MarketCpcLookupService::class));

        $search->refresh();

        $this->assertSame('7.50', $search->cpc_benchmark);
        $this->assertSame('google_ads', $search->cpc_source);
        $this->assertNotEmpty($search->cpc_keywords);

        $this->assertDatabaseHas('market_cpc_defaults', [
            'niche' => 'dental practice',
            'city' => 'birmingham',
            'cpc_benchmark' => '7.50',
            'cpc_source' => 'google_ads',
        ]);
    }

    public function test_does_not_overwrite_existing_cpc(): void
    {
        Http::fake();

        $search = Search::factory()->create([
            'cpc_benchmark' => 5.00,
            'cpc_source' => 'manual',
        ]);

        (new FetchSearchCpcJob($search))->handle(app(\App\Services\MarketCpcLookupService::class));

        $search->refresh();

        $this->assertSame('5.00', $search->cpc_benchmark);
        $this->assertSame('manual', $search->cpc_source);
        Http::assertNothingSent();
    }
}
