<?php

namespace Tests\Feature;

use App\Enums\AuditStatus;
use App\Enums\ScanType;
use App\Enums\SearchStatus;
use App\Jobs\AuditSiteJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Support\AuditingQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BackfillAuditsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function incompleteProspect(): Prospect
    {
        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'scan_type' => ScanType::Combined,
            'status' => SearchStatus::Complete,
        ]);

        return Prospect::factory()->create([
            'search_id' => $search->id,
            'website_url' => 'https://example.com',
            'audit_status' => AuditStatus::Complete,
            'a11y_score' => 55,
            'performance_score' => 42,
            'raw_a11y_payload' => ['violations' => []],
            'raw_lighthouse_payload' => null,
        ]);
    }

    public function test_dry_run_does_not_modify_prospects_or_dispatch_jobs(): void
    {
        Queue::fake();

        $prospect = $this->incompleteProspect();

        $this->artisan('scanner:backfill-audits')
            ->expectsOutputToContain('Found 1')
            ->assertExitCode(0);

        $prospect->refresh();
        $this->assertSame(AuditStatus::Complete, $prospect->audit_status);
        $this->assertNotNull($prospect->raw_a11y_payload);
        Queue::assertNothingPushed();
    }

    public function test_execute_resets_prospect_and_dispatches_audit_job(): void
    {
        Queue::fake();

        $prospect = $this->incompleteProspect();

        $this->artisan('scanner:backfill-audits', ['--execute' => true, '--delay' => 0])
            ->expectsOutputToContain('Dispatched 1')
            ->assertExitCode(0);

        $prospect->refresh();
        $this->assertSame(AuditStatus::Pending, $prospect->audit_status);
        $this->assertNull($prospect->raw_a11y_payload);
        $this->assertNull($prospect->raw_lighthouse_payload);
        $this->assertSame(0, $prospect->a11y_score);
        $this->assertSame(0, $prospect->performance_score);

        Queue::assertPushed(AuditSiteJob::class, function (AuditSiteJob $job, ?string $queue) use ($prospect) {
            return $job->prospect->id === $prospect->id
                && $queue === AuditingQueue::NAME;
        });
    }

    public function test_no_matches_exits_successfully(): void
    {
        $this->artisan('scanner:backfill-audits')
            ->expectsOutputToContain('No incomplete audits found')
            ->assertExitCode(0);
    }
}
