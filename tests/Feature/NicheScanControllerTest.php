<?php

namespace Tests\Feature;

use App\Models\NicheScan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NicheScanControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_latest_scan_per_niche_city(): void
    {
        $user = User::factory()->create();

        NicheScan::query()->create([
            'niche' => 'Dental Practice',
            'niche_query' => 'dental practice',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => '2026-05-20',
            'result_count' => 10,
            'sampled_count' => 5,
            'avg_gbp_score' => 50,
            'pct_no_website' => 20,
            'pct_low_reviews' => 40,
            'opportunity_score' => 45,
            'status' => 'complete',
            'ran_at' => now()->subWeek(),
        ]);

        NicheScan::query()->create([
            'niche' => 'Dental Practice',
            'niche_query' => 'dental practice',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => '2026-05-27',
            'result_count' => 30,
            'sampled_count' => 5,
            'avg_gbp_score' => 70,
            'pct_no_website' => 60,
            'pct_low_reviews' => 80,
            'opportunity_score' => 90,
            'status' => 'complete',
            'ran_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/niches')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Niches/Index')
                ->has('scans', 1)
                ->where('scans.0.result_count', 30)
                ->where('scans.0.opportunity_score', 90)
            );
    }

    public function test_trigger_queues_niches_scan_command(): void
    {
        $user = User::factory()->create();

        Artisan::shouldReceive('queue')
            ->once()
            ->with('niches:scan');

        $this->actingAs($user)
            ->post('/niches/scan')
            ->assertRedirect()
            ->assertSessionHas('success', 'Scan queued');
    }
}
