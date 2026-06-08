<?php

namespace Tests\Feature;

use App\Enums\NicheScanStatus;
use App\Models\NicheScan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NicheScanStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_when_todays_scan_is_pending(): void
    {
        $user = User::factory()->create();

        $previous = NicheScan::query()->create([
            'niche' => 'Plumber',
            'niche_query' => 'plumber',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => '2026-05-27',
            'result_count' => 100,
            'sampled_count' => 5,
            'avg_gbp_score' => 40,
            'pct_no_website' => 25,
            'pct_low_reviews' => 50,
            'opportunity_score' => 35,
            'status' => NicheScanStatus::Complete,
            'ran_at' => now()->subDay(),
        ]);

        $today = NicheScan::query()->create([
            'niche' => 'Plumber',
            'niche_query' => 'plumber',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => now('Europe/London')->toDateString(),
            'status' => NicheScanStatus::Pending,
        ]);

        $this->actingAs($user)
            ->getJson("/niches/{$previous->id}/status")
            ->assertOk()
            ->assertJson([
                'niche' => 'Plumber',
                'city' => 'Leeds',
                'id' => $today->id,
                'is_pending' => true,
                'status' => 'pending',
                'result_count' => 100,
                'opportunity_score' => 35,
            ]);
    }

    public function test_status_when_todays_scan_is_complete(): void
    {
        $user = User::factory()->create();

        $previous = NicheScan::query()->create([
            'niche' => 'Plumber',
            'niche_query' => 'plumber',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => '2026-05-27',
            'result_count' => 100,
            'status' => NicheScanStatus::Complete,
            'ran_at' => now()->subDay(),
        ]);

        $today = NicheScan::query()->create([
            'niche' => 'Plumber',
            'niche_query' => 'plumber',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => now('Europe/London')->toDateString(),
            'result_count' => 150,
            'sampled_count' => 5,
            'avg_gbp_score' => 55,
            'pct_no_website' => 30,
            'pct_low_reviews' => 60,
            'opportunity_score' => 48,
            'status' => NicheScanStatus::Complete,
            'ran_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson("/niches/{$previous->id}/status")
            ->assertOk()
            ->assertJson([
                'id' => $today->id,
                'is_pending' => false,
                'status' => 'complete',
                'result_count' => 150,
                'opportunity_score' => 48,
            ]);
    }

    public function test_status_when_todays_scan_failed(): void
    {
        $user = User::factory()->create();

        $previous = NicheScan::query()->create([
            'niche' => 'Plumber',
            'niche_query' => 'plumber',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => '2026-05-27',
            'result_count' => 100,
            'opportunity_score' => 35,
            'status' => NicheScanStatus::Complete,
            'ran_at' => now()->subDay(),
        ]);

        $today = NicheScan::query()->create([
            'niche' => 'Plumber',
            'niche_query' => 'plumber',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => now('Europe/London')->toDateString(),
            'status' => NicheScanStatus::Failed,
            'error_message' => 'API quota exceeded',
        ]);

        $this->actingAs($user)
            ->getJson("/niches/{$previous->id}/status")
            ->assertOk()
            ->assertJson([
                'id' => $today->id,
                'is_pending' => false,
                'status' => 'failed',
                'result_count' => 100,
                'opportunity_score' => 35,
                'error_message' => 'API quota exceeded',
            ]);
    }
}
