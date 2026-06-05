<?php

namespace Tests\Unit;

use App\Models\NicheScan;
use App\Queries\LatestNicheScanQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LatestNicheScanQueryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_ids_returns_latest_scan_per_niche_city(): void
    {
        $older = NicheScan::query()->create([
            'niche' => 'Dental Practice',
            'niche_query' => 'dental practice',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => '2026-05-20',
            'result_count' => 10,
            'status' => 'complete',
            'ran_at' => now()->subWeek(),
        ]);

        $latest = NicheScan::query()->create([
            'niche' => 'Dental Practice',
            'niche_query' => 'dental practice',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => '2026-05-27',
            'result_count' => 30,
            'status' => 'complete',
            'ran_at' => now(),
        ]);

        NicheScan::query()->create([
            'niche' => 'Dental Practice',
            'niche_query' => 'dental practice',
            'city' => 'Manchester',
            'country' => 'GB',
            'scan_date' => '2026-05-27',
            'result_count' => 12,
            'status' => 'complete',
            'ran_at' => now(),
        ]);

        $ids = LatestNicheScanQuery::ids()->all();

        $this->assertContains($latest->id, $ids);
        $this->assertNotContains($older->id, $ids);
        $this->assertCount(2, $ids);
    }

    #[Test]
    public function test_ranked_filter_limits_to_matching_rows(): void
    {
        NicheScan::query()->create([
            'niche' => 'Plumber',
            'niche_query' => 'plumber',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => '2026-05-20',
            'result_count' => 8,
            'status' => 'failed',
            'ran_at' => now(),
        ]);

        NicheScan::query()->create([
            'niche' => 'Plumber',
            'niche_query' => 'plumber',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => '2026-05-27',
            'result_count' => 22,
            'status' => 'complete',
            'ran_at' => now()->subDay(),
        ]);

        $counts = LatestNicheScanQuery::ranked(
            fn ($query) => $query->where('niche', 'Plumber')->where('status', 'complete'),
        )->pluck('result_count');

        $this->assertSame([22], $counts->all());
    }
}
