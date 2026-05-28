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

    public function test_index_paginates_latest_scan_per_niche_city(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 60; $i++) {
            NicheScan::query()->create([
                'niche' => "Niche {$i}",
                'niche_query' => "niche {$i}",
                'city' => 'Leeds',
                'country' => 'GB',
                'scan_date' => '2026-05-27',
                'result_count' => 10,
                'sampled_count' => 5,
                'avg_gbp_score' => 50,
                'pct_no_website' => 20,
                'pct_low_reviews' => 40,
                'opportunity_score' => 45 + $i,
                'status' => 'complete',
                'ran_at' => now()->subMinutes(60 - $i),
            ]);
        }

        $this->actingAs($user)
            ->get('/niches')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Niches/Index')
                ->has('scans', 50)
                ->where('pagination.total', 60)
                ->where('pagination.current_page', 1)
                ->where('pagination.per_page', 50)
                ->where('pagination.last_page', 2)
                ->where('scans.0.id', fn ($id) => $id !== null)
            );
    }

    public function test_index_filters_by_city(): void
    {
        $user = User::factory()->create();

        NicheScan::query()->create([
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
            'opportunity_score' => 90,
            'status' => 'complete',
            'ran_at' => now(),
        ]);

        NicheScan::query()->create([
            'niche' => 'Plumber',
            'niche_query' => 'plumber',
            'city' => 'Manchester',
            'country' => 'GB',
            'scan_date' => '2026-05-27',
            'result_count' => 10,
            'sampled_count' => 5,
            'avg_gbp_score' => 50,
            'pct_no_website' => 20,
            'pct_low_reviews' => 40,
            'opportunity_score' => 80,
            'status' => 'complete',
            'ran_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/niches?city=Leeds')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('scans', 1)
                ->where('pagination.total', 1)
                ->where('scans.0.city', 'Leeds')
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
