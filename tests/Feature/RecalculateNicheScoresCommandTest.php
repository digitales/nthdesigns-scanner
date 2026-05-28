<?php

namespace Tests\Feature;

use App\Models\NicheScan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RecalculateNicheScoresCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_recalculates_opportunity_scores_for_complete_rows(): void
    {
        $row = NicheScan::query()->create([
            'niche' => 'Spark',
            'niche_query' => 'spark',
            'city' => 'Gloucester',
            'country' => 'GB',
            'scan_date' => '2026-05-28',
            'result_count' => 1,
            'sampled_count' => 1,
            'avg_gbp_score' => 70,
            'pct_no_website' => 100,
            'pct_low_reviews' => 100,
            'opportunity_score' => 88,
            'status' => 'complete',
            'ran_at' => now(),
        ]);

        $this->assertSame(0, Artisan::call('niches:recalculate-scores'));

        $this->assertSame(0.0, $row->fresh()->opportunity_score);
    }

    public function test_dry_run_does_not_update_rows(): void
    {
        $row = NicheScan::query()->create([
            'niche' => 'Spark',
            'niche_query' => 'spark',
            'city' => 'Gloucester',
            'country' => 'GB',
            'scan_date' => '2026-05-28',
            'result_count' => 1,
            'sampled_count' => 1,
            'avg_gbp_score' => 70,
            'pct_no_website' => 100,
            'pct_low_reviews' => 100,
            'opportunity_score' => 88,
            'status' => 'complete',
            'ran_at' => now(),
        ]);

        $this->assertSame(0, Artisan::call('niches:recalculate-scores', ['--dry-run' => true]));

        $this->assertSame(88.0, $row->fresh()->opportunity_score);
    }
}
