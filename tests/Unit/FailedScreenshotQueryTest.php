<?php

namespace Tests\Unit;

use App\Jobs\CaptureScreenshotJob;
use App\Models\AuditJob;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use App\Models\User;
use App\Queries\FailedScreenshotQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FailedScreenshotQueryTest extends TestCase
{
    use RefreshDatabase;

    private function reportWithProspect(array $prospectAttrs = []): ProspectReport
    {
        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'status' => 'complete',
        ]);
        $prospect = Prospect::factory()->create(array_merge([
            'search_id' => $search->id,
            'website_url' => 'https://example.com',
            'audit_status' => 'complete',
        ], $prospectAttrs));

        return ProspectReport::factory()->create(['prospect_id' => $prospect->id]);
    }

    public function test_matches_failed_screenshot_job(): void
    {
        $report = $this->reportWithProspect();

        AuditJob::create([
            'prospect_id' => $report->prospect_id,
            'job_type' => 'screenshot',
            'status' => 'failed',
            'completed_at' => now(),
        ]);

        $ids = FailedScreenshotQuery::ids(stuckAfterMinutes: 15);

        $this->assertContains($report->id, $ids);
        $this->assertSame('screenshot failed', FailedScreenshotQuery::reasonFor($report));
    }

    public function test_matches_stale_running_screenshot_without_queue_job(): void
    {
        $this->useAuditingDatabaseQueue();

        $report = $this->reportWithProspect();

        AuditJob::create([
            'prospect_id' => $report->prospect_id,
            'job_type' => 'screenshot',
            'status' => 'running',
            'started_at' => now()->subMinutes(20),
        ]);

        $this->assertContains($report->id, FailedScreenshotQuery::ids(15));
    }

    public function test_does_not_match_when_screenshot_queue_job_present(): void
    {
        $this->useAuditingDatabaseQueue();

        $report = $this->reportWithProspect();

        AuditJob::create([
            'prospect_id' => $report->prospect_id,
            'job_type' => 'screenshot',
            'status' => 'running',
            'started_at' => now()->subMinutes(20),
        ]);

        CaptureScreenshotJob::dispatch($report);

        $this->assertNotContains($report->id, FailedScreenshotQuery::ids(15));
    }
}
