<?php

namespace Tests\Feature;

use App\Enums\NicheScanStatus;
use App\Jobs\ScanNicheJob;
use App\Models\NicheScan;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ProspectNicheScanTest extends TestCase
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
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'niche' => 'Dental Clinic',
            'city' => 'Leeds',
            'country' => 'GB',
        ]);
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);

        $this->actingAs($user)
            ->post("/prospects/{$prospect->id}/niche-scan")
            ->assertRedirect()
            ->assertSessionHas('success', 'Market scan queued for Dental Clinic in Leeds.');

        Queue::assertPushed(ScanNicheJob::class, fn (ScanNicheJob $job) => $job->niche === 'Dental Clinic'
            && $job->nicheQuery === 'dental clinic'
            && $job->city === 'Leeds'
            && $job->country === 'GB'
            && $job->force === true);
    }

    public function test_refresh_uses_lowercased_niche_when_not_in_config(): void
    {
        Queue::fake();
        config(['niches.niches' => []]);

        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'niche' => 'Custom Niche',
            'city' => 'Leeds',
        ]);
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);

        $this->actingAs($user)
            ->post("/prospects/{$prospect->id}/niche-scan")
            ->assertRedirect();

        Queue::assertPushed(ScanNicheJob::class, fn (ScanNicheJob $job) => $job->nicheQuery === 'custom niche');
    }

    public function test_refresh_blocked_when_todays_scan_is_pending(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'niche' => 'Plumber',
            'city' => 'Leeds',
        ]);
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);

        NicheScan::query()->create([
            'niche' => 'Plumber',
            'niche_query' => 'plumber',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => now('Europe/London')->toDateString(),
            'status' => NicheScanStatus::Pending,
        ]);

        $this->actingAs($user)
            ->post("/prospects/{$prospect->id}/niche-scan")
            ->assertRedirect()
            ->assertSessionHas('success', 'Market scan already in progress.');

        Queue::assertNothingPushed();
    }

    public function test_refresh_rate_limited_per_user_niche_and_city(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'niche' => 'Plumber',
            'city' => 'Leeds',
        ]);
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);

        RateLimiter::hit('prospect-niche-scan:'.$user->id.':Plumber:Leeds', 60);

        $this->actingAs($user)
            ->post("/prospects/{$prospect->id}/niche-scan")
            ->assertRedirect()
            ->assertSessionHas('error');

        Queue::assertNothingPushed();
    }

    public function test_refresh_rejects_direct_url_search(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $search = Search::factory()->directUrl()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);

        $this->actingAs($user)
            ->post("/prospects/{$prospect->id}/niche-scan")
            ->assertStatus(422);

        Queue::assertNothingPushed();
    }

    public function test_other_user_cannot_refresh_market_scan(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $owner->id])->id,
        ]);

        $this->actingAs($other)
            ->post("/prospects/{$prospect->id}/niche-scan")
            ->assertForbidden();
    }
}
