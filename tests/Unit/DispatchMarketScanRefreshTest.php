<?php

namespace Tests\Unit;

use App\Actions\DispatchMarketScanRefresh;
use App\Enums\NicheScanStatus;
use App\Jobs\ScanNicheJob;
use App\Models\NicheScan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class DispatchMarketScanRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_job_with_force(): void
    {
        Queue::fake();

        config([
            'niches.niches' => [
                ['label' => 'Dental Clinic', 'query' => 'dental clinic'],
            ],
        ]);

        $action = new DispatchMarketScanRefresh;
        $result = $action('Dental Clinic', 'Leeds', 'GB');

        $this->assertTrue($result->isQueued());

        Queue::assertPushed(ScanNicheJob::class, fn (ScanNicheJob $job) => $job->niche === 'Dental Clinic'
            && $job->nicheQuery === 'dental clinic'
            && $job->city === 'Leeds'
            && $job->country === 'GB'
            && $job->force === true);
    }

    public function test_uses_fallback_query_when_label_not_in_config(): void
    {
        Queue::fake();
        config(['niches.niches' => []]);

        $action = new DispatchMarketScanRefresh;
        $action('Custom Niche', 'Leeds', 'GB', 'stored query');

        Queue::assertPushed(ScanNicheJob::class, fn (ScanNicheJob $job) => $job->nicheQuery === 'stored query');
    }

    public function test_blocks_when_todays_scan_is_pending(): void
    {
        Queue::fake();

        NicheScan::query()->create([
            'niche' => 'Plumber',
            'niche_query' => 'plumber',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => now('Europe/London')->toDateString(),
            'status' => NicheScanStatus::Pending,
        ]);

        $action = new DispatchMarketScanRefresh;
        $result = $action('Plumber', 'Leeds', 'GB');

        $this->assertTrue($result->isAlreadyPending());
        Queue::assertNothingPushed();
    }

    public function test_rate_limits_per_user_niche_and_city(): void
    {
        Queue::fake();

        RateLimiter::hit(DispatchMarketScanRefresh::rateLimitKey(1, 'Plumber', 'Leeds'), 60);

        $action = new DispatchMarketScanRefresh;
        $result = $action('Plumber', 'Leeds', 'GB', null, 1);

        $this->assertTrue($result->isRateLimited());
        $this->assertGreaterThan(0, $result->rateLimitSeconds);
        Queue::assertNothingPushed();
    }
}
