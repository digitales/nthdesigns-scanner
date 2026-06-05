<?php

namespace Tests\Unit;

use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Queries\IncompleteAuditQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class IncompleteAuditQueryTest extends TestCase
{
    use RefreshDatabase;

    private function prospectForSearch(array $searchAttrs, array $prospectAttrs): Prospect
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(array_merge([
            'user_id'   => $user->id,
            'scan_type' => 'combined',
            'status'    => 'complete',
        ], $searchAttrs));

        return Prospect::factory()->create(array_merge([
            'search_id'   => $search->id,
            'website_url' => 'https://example.com',
        ], $prospectAttrs));
    }

    public function test_matches_complete_prospect_with_null_lighthouse_payload(): void
    {
        $prospect = $this->prospectForSearch([], [
            'audit_status'          => 'complete',
            'raw_a11y_payload'      => ['violations' => []],
            'raw_lighthouse_payload'=> null,
        ]);

        $ids = IncompleteAuditQuery::ids();

        $this->assertContains($prospect->id, $ids);
        $this->assertSame('missing raw_lighthouse_payload', IncompleteAuditQuery::reasonFor($prospect));
    }

    public function test_does_not_match_when_lighthouse_json_present_with_zero_performance(): void
    {
        $prospect = $this->prospectForSearch([], [
            'audit_status'          => 'complete',
            'performance_score'     => 0,
            'raw_a11y_payload'      => ['violations' => []],
            'raw_lighthouse_payload'=> ['performance' => 0],
        ]);

        $this->assertNotContains($prospect->id, IncompleteAuditQuery::ids());
    }

    public function test_excludes_search_with_failed_status(): void
    {
        $prospect = $this->prospectForSearch(['status' => 'failed'], [
            'audit_status'          => 'complete',
            'raw_lighthouse_payload'=> null,
        ]);

        $this->assertNotContains($prospect->id, IncompleteAuditQuery::ids());
    }

    public function test_excludes_gbp_only_scan(): void
    {
        $prospect = $this->prospectForSearch(['scan_type' => 'gbp_only'], [
            'audit_status'          => 'complete',
            'raw_lighthouse_payload'=> null,
        ]);

        $this->assertNotContains($prospect->id, IncompleteAuditQuery::ids());
    }

    public function test_excludes_pending_audit_status(): void
    {
        $prospect = $this->prospectForSearch([], [
            'audit_status'          => 'pending',
            'raw_lighthouse_payload'=> null,
        ]);

        $this->assertNotContains($prospect->id, IncompleteAuditQuery::ids());
    }

    public function test_matches_complete_prospect_with_site_load_error_in_payload(): void
    {
        $prospect = $this->prospectForSearch([], [
            'audit_status'           => 'complete',
            'raw_a11y_payload'       => ['error' => 'page.goto: Timeout', 'violations' => []],
            'raw_lighthouse_payload' => ['performance' => 50],
            'performance_score'      => 50,
        ]);

        $this->assertContains($prospect->id, IncompleteAuditQuery::ids());
        $this->assertStringStartsWith('site load error:', IncompleteAuditQuery::reasonFor($prospect));
    }

    public function test_matches_failed_with_missing_a11y_payload(): void
    {
        $prospect = $this->prospectForSearch([], [
            'audit_status'     => 'failed',
            'raw_a11y_payload' => null,
        ]);

        $this->assertContains($prospect->id, IncompleteAuditQuery::ids());
        $this->assertSame('missing raw_a11y_payload', IncompleteAuditQuery::reasonFor($prospect));
    }

    public function test_excludes_skipped_audit_status(): void
    {
        Config::set('scanner.audit_driver', 'skip');

        $prospect = $this->prospectForSearch([], [
            'audit_status'           => 'skipped',
            'raw_lighthouse_payload' => null,
        ]);

        $this->assertNotContains($prospect->id, IncompleteAuditQuery::ids());
    }
}
