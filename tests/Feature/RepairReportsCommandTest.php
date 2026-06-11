<?php

namespace Tests\Feature;

use App\Enums\AuditStatus;
use App\Enums\ScanType;
use App\Enums\SearchStatus;
use App\Jobs\CombineScoresJob;
use App\Jobs\GenerateProspectReportJob;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use App\Models\User;
use App\Support\AuditingQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RepairReportsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function searchProspect(string $auditStatus, array $extra = []): Prospect
    {
        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'scan_type' => ScanType::Combined,
            'status' => SearchStatus::Auditing,
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

        $missingReport = $this->searchProspect(AuditStatus::Complete->value, [
            'combined_score' => 40,
        ]);

        $stuckCombine = $this->searchProspect(AuditStatus::Pending->value, [
            'raw_a11y_payload' => ['error' => 'page.goto: Timeout', 'violations' => []],
            'a11y_score' => 50,
        ]);

        $this->artisan('scanner:repair-reports')
            ->expectsOutputToContain('reports:')
            ->expectsOutputToContain('combine:')
            ->assertExitCode(0);

        Queue::assertNothingPushed();

        $this->assertSame($missingReport->id, $missingReport->fresh()->id);
        $this->assertSame($stuckCombine->id, $stuckCombine->fresh()->id);
    }

    public function test_execute_dispatches_combine_and_report_jobs(): void
    {
        Queue::fake();
        $this->useAuditingDatabaseQueue();

        $missingReport = $this->searchProspect(AuditStatus::Complete->value, [
            'combined_score' => 40,
        ]);

        $stuckCombine = $this->searchProspect(AuditStatus::Pending->value, [
            'raw_a11y_payload' => ['error' => 'page.goto: Timeout', 'violations' => []],
            'a11y_score' => 50,
        ]);

        $this->artisan('scanner:repair-reports', [
            '--execute' => true,
            '--delay' => 0,
        ])->assertExitCode(0);

        Queue::assertPushed(CombineScoresJob::class, function (CombineScoresJob $job) use ($stuckCombine) {
            return $job->prospect->id === $stuckCombine->id;
        });

        Queue::assertPushed(GenerateProspectReportJob::class, function (GenerateProspectReportJob $job, ?string $queue) use ($missingReport) {
            return $job->prospect->id === $missingReport->id
                && $queue === AuditingQueue::NAME;
        });
    }

    public function test_only_reports_skips_combine(): void
    {
        Queue::fake();
        $this->useAuditingDatabaseQueue();

        $missingReport = $this->searchProspect(AuditStatus::Complete->value);
        $this->searchProspect(AuditStatus::Pending->value, [
            'raw_a11y_payload' => ['violations' => []],
        ]);

        $this->artisan('scanner:repair-reports', [
            '--execute' => true,
            '--only' => 'reports',
            '--delay' => 0,
        ])->assertExitCode(0);

        Queue::assertPushed(GenerateProspectReportJob::class, fn (GenerateProspectReportJob $job) => $job->prospect->id === $missingReport->id);
        Queue::assertNotPushed(CombineScoresJob::class);
    }

    public function test_skips_prospects_that_already_have_reports(): void
    {
        Queue::fake();
        $this->useAuditingDatabaseQueue();

        $prospect = $this->searchProspect(AuditStatus::Complete->value);
        ProspectReport::factory()->create(['prospect_id' => $prospect->id]);

        $this->artisan('scanner:repair-reports', [
            '--execute' => true,
            '--delay' => 0,
        ])->assertExitCode(0);

        Queue::assertNotPushed(GenerateProspectReportJob::class);
    }

    public function test_no_matches_exits_successfully(): void
    {
        $this->artisan('scanner:repair-reports')
            ->expectsOutputToContain('Nothing to repair')
            ->assertExitCode(0);
    }
}
