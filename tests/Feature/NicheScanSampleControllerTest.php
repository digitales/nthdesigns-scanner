<?php

namespace Tests\Feature;

use App\Jobs\ScanNicheJob;
use App\Models\NicheScan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NicheScanSampleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_sample_when_present(): void
    {
        $user = User::factory()->create();

        $scan = NicheScan::query()->create([
            'niche' => 'Dental Practice',
            'niche_query' => 'dental practice',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => '2026-05-27',
            'result_count' => 10,
            'sampled_count' => 1,
            'avg_gbp_score' => 50,
            'pct_no_website' => 100,
            'pct_low_reviews' => 100,
            'opportunity_score' => 70,
            'status' => 'complete',
            'ran_at' => now(),
            'sample_preview' => [
                ['name' => 'Joe\'s Dental', 'gbp_score' => 72, 'no_website' => true, 'review_count' => 5],
            ],
        ]);

        $this->actingAs($user)
            ->getJson("/niches/{$scan->id}/sample")
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('items.0.name', 'Joe\'s Dental');
    }

    public function test_show_dispatches_job_when_preview_missing(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $scan = NicheScan::query()->create([
            'niche' => 'Dental Practice',
            'niche_query' => 'dental practice',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => '2026-05-27',
            'result_count' => 10,
            'sampled_count' => 5,
            'avg_gbp_score' => 50,
            'pct_no_website' => 20,
            'pct_low_reviews' => 40,
            'opportunity_score' => 70,
            'status' => 'complete',
            'ran_at' => now(),
            'sample_preview' => null,
        ]);

        $this->actingAs($user)
            ->getJson("/niches/{$scan->id}/sample")
            ->assertStatus(202)
            ->assertJsonPath('status', 'loading');

        Queue::assertPushed(ScanNicheJob::class, fn (ScanNicheJob $job) => $job->niche === $scan->niche && $job->city === $scan->city);
    }

    public function test_show_returns_loading_when_pending_without_dispatch(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $scan = NicheScan::query()->create([
            'niche' => 'Dental Practice',
            'niche_query' => 'dental practice',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => '2026-05-27',
            'result_count' => 0,
            'sampled_count' => 0,
            'status' => 'pending',
            'sample_preview' => null,
        ]);

        $this->actingAs($user)
            ->getJson("/niches/{$scan->id}/sample")
            ->assertStatus(202)
            ->assertJsonPath('status', 'loading');

        Queue::assertNothingPushed();
    }
}
