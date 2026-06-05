<?php

namespace Tests\Unit;

use App\Models\AuditJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Queries\FailedSiteAuditQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class FailedSiteAuditQueryTest extends TestCase
{
    use RefreshDatabase;

    private function failedProspect(array $prospectAttrs = []): Prospect
    {
        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'scan_type' => 'combined',
            'status' => 'complete',
        ]);

        return Prospect::factory()->create(array_merge([
            'search_id' => $search->id,
            'website_url' => 'https://example.com',
            'audit_status' => 'failed',
            'raw_a11y_payload' => ['violations' => []],
            'raw_lighthouse_payload' => ['performance' => 80],
            'performance_score' => 80,
        ], $prospectAttrs));
    }

    public function test_matches_failed_even_with_complete_payloads(): void
    {
        $prospect = $this->failedProspect();

        $this->assertContains($prospect->id, FailedSiteAuditQuery::ids());
        $this->assertStringStartsWith('audit_status failed', FailedSiteAuditQuery::reasonFor($prospect));
    }

    public function test_appends_latest_error_message(): void
    {
        $prospect = $this->failedProspect();

        AuditJob::create([
            'prospect_id' => $prospect->id,
            'job_type' => 'accessibility',
            'status' => 'failed',
            'error_message' => 'Playwright timeout',
            'completed_at' => now(),
        ]);

        $this->assertStringContainsString('Playwright timeout', FailedSiteAuditQuery::reasonFor($prospect));
    }

    public function test_excludes_stuck_prospect_ids(): void
    {
        $prospect = $this->failedProspect();

        $this->assertNotContains($prospect->id, FailedSiteAuditQuery::ids(excludeProspectIds: [$prospect->id]));
    }

    public function test_excludes_when_audit_driver_skip(): void
    {
        Config::set('scanner.audit_driver', 'skip');

        $prospect = $this->failedProspect();

        $this->assertNotContains($prospect->id, FailedSiteAuditQuery::ids());
    }
}
