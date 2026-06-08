<?php

namespace Tests\Feature;

use App\Actions\DispatchMarketScanRefresh;
use App\Enums\NicheScanStatus;
use App\Jobs\ScanNicheJob;
use App\Models\NicheScan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class NicheScanRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_dispatches_scan_niche_job_with_force(): void
    {
        Queue::fake();

        config([
            'niches.niches' => [
                ['label' => 'Dental Clinic', 'query' => 'dental clinic'],
            ],
        ]);

        $user = User::factory()->create();
        $scan = NicheScan::query()->create([
            'niche' => 'Dental Clinic',
            'niche_query' => 'dental clinic',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => '2026-05-27',
            'result_count' => 10,
            'sampled_count' => 5,
            'avg_gbp_score' => 50,
            'pct_no_website' => 20,
            'pct_low_reviews' => 40,
            'opportunity_score' => 45,
            'status' => NicheScanStatus::Complete,
            'ran_at' => now(),
        ]);

        $this->actingAs($user)
            ->post("/niches/{$scan->id}/refresh")
            ->assertRedirect()
            ->assertSessionHas('success', 'Market scan queued for Dental Clinic in Leeds.');

        Queue::assertPushed(ScanNicheJob::class, fn (ScanNicheJob $job) => $job->niche === 'Dental Clinic'
            && $job->nicheQuery === 'dental clinic'
            && $job->city === 'Leeds'
            && $job->force === true);
    }

    public function test_refresh_uses_stored_niche_query_when_label_not_in_config(): void
    {
        Queue::fake();
        config(['niches.niches' => []]);

        $user = User::factory()->create();
        $scan = NicheScan::query()->create([
            'niche' => 'Custom Niche',
            'niche_query' => 'stored query',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => '2026-05-27',
            'status' => NicheScanStatus::Complete,
            'ran_at' => now(),
        ]);

        $this->actingAs($user)
            ->post("/niches/{$scan->id}/refresh")
            ->assertRedirect();

        Queue::assertPushed(ScanNicheJob::class, fn (ScanNicheJob $job) => $job->nicheQuery === 'stored query');
    }

    public function test_refresh_blocked_when_todays_scan_is_pending(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $scan = NicheScan::query()->create([
            'niche' => 'Plumber',
            'niche_query' => 'plumber',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => '2026-05-27',
            'status' => NicheScanStatus::Complete,
            'ran_at' => now()->subDay(),
        ]);

        NicheScan::query()->create([
            'niche' => 'Plumber',
            'niche_query' => 'plumber',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => now('Europe/London')->toDateString(),
            'status' => NicheScanStatus::Pending,
        ]);

        $this->actingAs($user)
            ->post("/niches/{$scan->id}/refresh")
            ->assertRedirect()
            ->assertSessionHas('success', 'Market scan already in progress.');

        Queue::assertNothingPushed();
    }

    public function test_refresh_rate_limited_per_user_niche_and_city(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $scan = NicheScan::query()->create([
            'niche' => 'Plumber',
            'niche_query' => 'plumber',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => '2026-05-27',
            'status' => NicheScanStatus::Complete,
            'ran_at' => now(),
        ]);

        RateLimiter::hit(DispatchMarketScanRefresh::rateLimitKey($user->id, 'Plumber', 'Leeds'), 60);

        $this->actingAs($user)
            ->post("/niches/{$scan->id}/refresh")
            ->assertRedirect()
            ->assertSessionHas('error');

        Queue::assertNothingPushed();
    }

    public function test_refresh_requires_authentication(): void
    {
        $scan = NicheScan::query()->create([
            'niche' => 'Plumber',
            'niche_query' => 'plumber',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => '2026-05-27',
            'status' => NicheScanStatus::Complete,
            'ran_at' => now(),
        ]);

        $this->post("/niches/{$scan->id}/refresh")
            ->assertRedirect('/login');
    }
}
