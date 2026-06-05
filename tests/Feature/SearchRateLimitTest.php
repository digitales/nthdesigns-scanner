<?php

namespace Tests\Feature;

use App\Jobs\AuditSiteJob;
use App\Jobs\ScorePlaceJob;
use App\Jobs\ScrapeProspectsJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SearchRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_submission_is_rate_limited(): void
    {
        Bus::fake([ScrapeProspectsJob::class, AuditSiteJob::class, ScorePlaceJob::class]);

        config(['scanner.search_rate_limit_seconds' => 30]);

        $user = User::factory()->create();
        RateLimiter::clear('search-submit:'.$user->id);

        $payload = [
            'niche' => 'dental',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_type' => 'combined',
        ];

        $this->actingAs($user)->post('/searches', $payload)->assertRedirect();

        $this->actingAs($user)
            ->post('/searches', $payload)
            ->assertSessionHasErrors('niche');
    }

    public function test_direct_url_shares_rate_limit_with_area_search(): void
    {
        Bus::fake([ScrapeProspectsJob::class, AuditSiteJob::class, ScorePlaceJob::class]);

        config(['scanner.search_rate_limit_seconds' => 30]);

        $user = User::factory()->create();
        RateLimiter::clear('search-submit:'.$user->id);

        $this->actingAs($user)->post('/searches', [
            'niche' => 'dental',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_type' => 'combined',
        ])->assertRedirect();

        $this->actingAs($user)
            ->post('/searches/direct', ['website_url' => 'https://example.com'])
            ->assertSessionHasErrors('website_url');
    }
}
