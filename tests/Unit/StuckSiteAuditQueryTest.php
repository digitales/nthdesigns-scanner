<?php

namespace Tests\Unit;

use App\Jobs\AuditSiteJob;
use App\Models\AuditJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Queries\StuckSiteAuditQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class StuckSiteAuditQueryTest extends TestCase
{
    use RefreshDatabase;

    private function stalePendingProspect(array $searchAttrs = [], array $prospectAttrs = []): Prospect
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(array_merge([
            'user_id' => $user->id,
            'scan_type' => 'combined',
            'status' => 'auditing',
        ], $searchAttrs));

        $prospect = Prospect::factory()->create(array_merge([
            'search_id' => $search->id,
            'website_url' => 'https://example.com',
            'audit_status' => 'pending',
        ], $prospectAttrs));

        $prospect->forceFill(['updated_at' => now()->subMinutes(20)])->save();

        return $prospect->fresh();
    }

    public function test_matches_stale_pending_without_queue_job(): void
    {
        $this->useAuditingDatabaseQueue();

        $prospect = $this->stalePendingProspect();

        $ids = StuckSiteAuditQuery::ids(stuckAfterMinutes: 15);

        $this->assertContains($prospect->id, $ids);
        $this->assertStringContainsString('pending without queue job', StuckSiteAuditQuery::reasonFor($prospect, 15));
    }

    public function test_does_not_match_fresh_pending(): void
    {
        $this->useAuditingDatabaseQueue();

        $prospect = $this->stalePendingProspect();
        $prospect->forceFill(['updated_at' => now()->subMinutes(5)])->save();

        $this->assertNotContains($prospect->id, StuckSiteAuditQuery::ids(15));
    }

    public function test_does_not_match_when_queue_job_present(): void
    {
        $this->useAuditingDatabaseQueue();

        $prospect = $this->stalePendingProspect();

        AuditSiteJob::dispatch($prospect);

        $this->assertNotContains($prospect->id, StuckSiteAuditQuery::ids(15));
    }

    public function test_matches_stale_running_accessibility_audit_job(): void
    {
        $this->useAuditingDatabaseQueue();

        $prospect = $this->stalePendingProspect();

        AuditJob::create([
            'prospect_id' => $prospect->id,
            'job_type' => 'accessibility',
            'status' => 'running',
            'started_at' => now()->subMinutes(20),
        ]);

        $this->assertContains($prospect->id, StuckSiteAuditQuery::ids(15));
        $this->assertStringContainsString('running audit_job', StuckSiteAuditQuery::reasonFor($prospect, 15));
    }

    public function test_excludes_when_audit_driver_skip(): void
    {
        Config::set('scanner.audit_driver', 'skip');
        $this->useAuditingDatabaseQueue();

        $prospect = $this->stalePendingProspect();

        $this->assertNotContains($prospect->id, StuckSiteAuditQuery::ids(15));
    }
}
