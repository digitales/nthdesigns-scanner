<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MarketCpcDefaultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketCpcControllerTest extends TestCase
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

    public function test_fetch_saves_market_default_without_running_search(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token', 'expires_in' => 3600]),
            'googleads.googleapis.com/*' => Http::response([
                'results' => [
                    ['keywordIdeaMetrics' => ['averageCpcMicros' => '8500000']],
                ],
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/search')
            ->post('/market-cpc/fetch', [
                'niche' => 'dental practice',
                'city' => 'Birmingham',
                'country' => 'GB',
            ])
            ->assertRedirect('/search')
            ->assertSessionHas('success')
            ->assertSessionHas('market_cpc');

        $this->assertDatabaseMissing('searches', ['user_id' => $user->id]);
        $this->assertDatabaseHas('market_cpc_defaults', [
            'user_id' => $user->id,
            'niche' => 'dental practice',
            'city' => 'birmingham',
            'cpc_benchmark' => '8.50',
            'cpc_source' => 'google_ads',
        ]);
    }

    public function test_load_reads_saved_default_without_external_api(): void
    {
        Http::fake();

        $user = User::factory()->create();

        app(MarketCpcDefaultService::class)->upsert($user, 'dental practice', 'Leeds', 'GB', [
            'cpc_benchmark' => 9.25,
            'cpc_source' => 'manual',
            'cpc_keywords' => ['dentist leeds'],
        ]);

        $this->actingAs($user)
            ->from('/search')
            ->post('/market-cpc/load', [
                'niche' => 'dental practice',
                'city' => 'Leeds',
                'country' => 'GB',
            ])
            ->assertRedirect('/search')
            ->assertSessionHas('market_cpc.cpc_benchmark', '9.25');

        Http::assertNothingSent();
    }
}
