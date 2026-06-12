<?php

namespace Tests\Unit;

use App\Enums\AuditJobStatus;
use App\Enums\AuditStatus;
use App\Enums\ScanType;
use App\Jobs\CombineScoresJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Services\SiteScanFailureRecorder;
use App\Services\SiteScanPreflightGate;
use App\Services\WebsiteReachabilityService;
use App\Support\ReachabilityResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SiteScanPreflightGateTest extends TestCase
{
    use RefreshDatabase;

    private function pendingProspect(): Prospect
    {
        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'scan_type' => ScanType::Combined,
        ]);

        return Prospect::factory()->create([
            'search_id' => $search->id,
            'website_url' => 'https://dead.example',
            'gbp_score' => 42,
            'audit_status' => AuditStatus::Pending,
        ]);
    }

    public function test_unreachable_site_records_failure_and_dispatches_combine(): void
    {
        Queue::fake([CombineScoresJob::class]);

        $prospect = $this->pendingProspect();

        $this->mock(WebsiteReachabilityService::class, function ($mock) {
            $mock->shouldReceive('check')
                ->once()
                ->with('https://dead.example')
                ->andReturn(ReachabilityResult::failed('Could not resolve host', permanent: true));
        });

        $passed = app(SiteScanPreflightGate::class)->passOrFail($prospect);

        $this->assertFalse($passed);

        $prospect->refresh();

        $this->assertSame(AuditStatus::Failed, $prospect->audit_status);
        $this->assertSame(['Site unreachable'], $prospect->a11y_flags);
        $this->assertTrue($prospect->raw_a11y_payload['preflight_failed']);
        $this->assertSame(0, $prospect->a11y_score);

        $this->assertDatabaseHas('audit_jobs', [
            'prospect_id' => $prospect->id,
            'job_type' => 'accessibility',
            'status' => 'failed',
        ]);

        Queue::assertPushed(CombineScoresJob::class);
    }

    public function test_reachable_site_passes_gate(): void
    {
        Queue::fake([CombineScoresJob::class]);

        $prospect = $this->pendingProspect();

        $this->mock(WebsiteReachabilityService::class, function ($mock) {
            $mock->shouldReceive('check')
                ->once()
                ->andReturn(ReachabilityResult::ok());
        });

        $this->mock(SiteScanFailureRecorder::class, function ($mock) {
            $mock->shouldNotReceive('recordPreflightFailure');
        });

        $passed = app(SiteScanPreflightGate::class)->passOrFail($prospect);

        $this->assertTrue($passed);
        Queue::assertNotPushed(CombineScoresJob::class);
    }
}
