<?php

namespace Tests\Feature;

use App\Jobs\AuditSiteJob;
use App\Jobs\CaptureScreenshotJob;
use App\Models\AuditJob;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use App\Models\User;
use App\Support\AuditingQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RepairAuditsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function searchProspect(string $auditStatus, array $extra = []): Prospect
    {
        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'scan_type' => 'combined',
            'status' => 'auditing',
        ]);

        return Prospect::factory()->create(array_merge([
            'search_id' => $search->id,
            'website_url' => 'https://example.com',
            'audit_status' => $auditStatus,
        ], $extra));
    }

    public function test_dry_run_lists_categories_without_dispatching(): void
    {
        Queue::fake();
        $this->useAuditingDatabaseQueue();

        $stuck = $this->searchProspect('pending');
        $stuck->forceFill(['updated_at' => now()->subMinutes(20)])->save();

        $failed = $this->searchProspect('failed');

        $report = ProspectReport::factory()->create(['prospect_id' => $failed->id]);
        AuditJob::create([
            'prospect_id' => $failed->id,
            'job_type' => 'screenshot',
            'status' => 'failed',
            'completed_at' => now(),
        ]);

        $this->artisan('scanner:repair-audits', ['--stuck-after' => 15])
            ->expectsOutputToContain('stuck:')
            ->expectsOutputToContain('failed:')
            ->expectsOutputToContain('screenshots:')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_execute_stuck_closes_running_job_and_dispatches_audit(): void
    {
        Queue::fake();
        $this->useAuditingDatabaseQueue();

        $prospect = $this->searchProspect('pending');
        $prospect->forceFill(['updated_at' => now()->subMinutes(20)])->save();

        $running = AuditJob::create([
            'prospect_id' => $prospect->id,
            'job_type' => 'accessibility',
            'status' => 'running',
            'started_at' => now()->subMinutes(20),
        ]);

        $this->artisan('scanner:repair-audits', [
            '--execute' => true,
            '--only' => 'stuck',
            '--stuck-after' => 15,
            '--delay' => 0,
        ])->assertExitCode(0);

        $running->refresh();
        $this->assertSame('failed', $running->status);
        $this->assertSame('Closed by scanner:repair-audits (stale)', $running->error_message);

        Queue::assertPushed(AuditSiteJob::class, fn (AuditSiteJob $job) => $job->prospect->id === $prospect->id);
    }

    public function test_execute_failed_resets_and_dispatches_audit(): void
    {
        Queue::fake();

        $prospect = $this->searchProspect('failed', [
            'raw_a11y_payload' => ['violations' => []],
            'raw_lighthouse_payload' => ['performance' => 90],
            'performance_score' => 90,
        ]);

        $this->artisan('scanner:repair-audits', [
            '--execute' => true,
            '--only' => 'failed',
            '--delay' => 0,
        ])->assertExitCode(0);

        $prospect->refresh();
        $this->assertSame('pending', $prospect->audit_status);
        $this->assertNull($prospect->raw_a11y_payload);

        Queue::assertPushed(AuditSiteJob::class);
    }

    public function test_execute_screenshot_dispatches_capture_job_only(): void
    {
        Queue::fake();

        $prospect = $this->searchProspect('complete');
        $report = ProspectReport::factory()->create(['prospect_id' => $prospect->id]);

        AuditJob::create([
            'prospect_id' => $prospect->id,
            'job_type' => 'screenshot',
            'status' => 'failed',
            'completed_at' => now(),
        ]);

        $this->artisan('scanner:repair-audits', [
            '--execute' => true,
            '--only' => 'screenshots',
            '--delay' => 0,
        ])->assertExitCode(0);

        $prospect->refresh();
        $this->assertSame('complete', $prospect->audit_status);

        Queue::assertPushed(CaptureScreenshotJob::class, function (CaptureScreenshotJob $job, ?string $queue) use ($report) {
            return $job->report->id === $report->id
                && $queue === AuditingQueue::NAME;
        });
        Queue::assertNotPushed(AuditSiteJob::class);
    }

    public function test_no_matches_exits_successfully(): void
    {
        $this->artisan('scanner:repair-audits')
            ->expectsOutputToContain('Nothing to repair')
            ->assertExitCode(0);
    }
}
