<?php

namespace Tests\Feature;

use App\Enums\AuditStatus;
use App\Enums\ScanType;
use App\Jobs\AuditSiteJob;
use App\Jobs\CombineScoresJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Services\A11yScoringService;
use App\Services\AuditErrorRecorder;
use App\Services\AuditRunnerService;
use App\Services\CmsDetectionRunnerService;
use App\Services\ScreenshotStorageService;
use App\Services\SearchStatusService;
use App\Services\SiteScanPreflightGate;
use App\Services\WebsiteReachabilityService;
use App\Support\ReachabilityResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SiteScanPreflightJobTest extends TestCase
{
    use RefreshDatabase;

    private function pendingProspect(): Prospect
    {
        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'scan_type' => ScanType::Combined,
            'total_found' => 1,
        ]);

        return Prospect::factory()->create([
            'search_id' => $search->id,
            'website_url' => 'https://franklynandco.uk/',
            'gbp_score' => 55,
            'audit_status' => AuditStatus::Pending,
        ]);
    }

    public function test_audit_job_stops_before_runner_when_preflight_fails(): void
    {
        Queue::fake([CombineScoresJob::class]);

        $prospect = $this->pendingProspect();

        $this->mock(SiteScanPreflightGate::class, function ($mock) {
            $mock->shouldReceive('passOrFail')
                ->once()
                ->with(\Mockery::type(\App\Models\Prospect::class))
                ->andReturn(false);
        });

        $this->mock(AuditRunnerService::class, function ($mock) {
            $mock->shouldReceive('shouldSkip')->andReturn(false);
            $mock->shouldNotReceive('run');
        });

        (new AuditSiteJob($prospect))->handle(
            app(AuditRunnerService::class),
            app(A11yScoringService::class),
            app(SearchStatusService::class),
            app(ScreenshotStorageService::class),
            app(AuditErrorRecorder::class),
            app(CmsDetectionRunnerService::class),
            app(SiteScanPreflightGate::class),
        );
    }

    public function test_preflight_failure_end_to_end(): void
    {
        Queue::fake([CombineScoresJob::class]);

        $prospect = $this->pendingProspect();

        $this->mock(WebsiteReachabilityService::class, function ($mock) {
            $mock->shouldReceive('check')
                ->once()
                ->andReturn(ReachabilityResult::failed('Could not resolve host', permanent: true));
        });

        $this->mock(AuditRunnerService::class, function ($mock) {
            $mock->shouldReceive('shouldSkip')->andReturn(false);
            $mock->shouldNotReceive('run');
        });

        (new AuditSiteJob($prospect))->handle(
            app(AuditRunnerService::class),
            app(A11yScoringService::class),
            app(SearchStatusService::class),
            app(ScreenshotStorageService::class),
            app(AuditErrorRecorder::class),
            app(CmsDetectionRunnerService::class),
            app(SiteScanPreflightGate::class),
        );

        $prospect->refresh();

        $this->assertSame(AuditStatus::Failed, $prospect->audit_status);
        $this->assertSame(['Site unreachable'], $prospect->a11y_flags);
        Queue::assertPushed(CombineScoresJob::class);
    }
}
