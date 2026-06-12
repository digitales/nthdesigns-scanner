<?php

namespace Tests\Feature;

use App\Enums\ListItemStatus;
use App\Enums\NicheScanStatus;
use App\Enums\ProspectListType;
use App\Models\AuditJob;
use App\Models\NicheScan;
use App\Models\AuditJobErrorDetail;
use App\Models\OutreachSelection;
use App\Models\Prospect;
use App\Models\ProspectList;
use App\Models\ProspectListItem;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProspectShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_includes_audit_when_complete_with_payload(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'audit_status' => 'complete',
            'website_url' => 'https://example.com',
            'performance_score' => 50,
            'raw_a11y_payload' => [
                'url' => 'https://example.com',
                'violations' => [
                    ['id' => 'color-contrast', 'impact' => 'critical', 'description' => 'Contrast', 'nodes' => [1]],
                ],
                'pass_count' => 10,
                'incomplete_count' => 1,
            ],
            'raw_lighthouse_payload' => ['performance' => 50, 'accessibility' => 60, 'seo' => 70],
        ]);
        AuditJob::create([
            'prospect_id' => $prospect->id,
            'job_type' => 'accessibility',
            'status' => 'complete',
            'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get("/prospects/{$prospect->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Prospect/Show')
                ->has('audit')
                ->where('audit.summary.critical', 1)
                ->where('audit.pass_count', 10)
                ->where('lighthouse.performance', 50)
                ->where('lighthouse.accessibility', 60));
    }

    public function test_show_includes_cms_when_detection_present(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'website_url' => 'https://example.com',
            'cms_detection' => [
                'platform' => 'wordpress',
                'version' => '6.4.2',
                'confidence' => 'high',
                'signals' => [['id' => 'meta_generator', 'matched' => true, 'detail' => 'WordPress 6.4.2']],
                'detected_at' => now()->toIso8601String(),
                'url' => 'https://example.com',
            ],
        ]);

        $this->actingAs($user)
            ->get("/prospects/{$prospect->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('cms')
                ->where('cms.label', 'WordPress 6.4')
                ->where('cms.badge', 'WP')
                ->where('cms.pending', false));
    }

    public function test_show_includes_audit_failure_with_full_diagnostic(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'audit_status' => 'failed',
            'website_url' => 'https://example.com',
        ]);
        $job = AuditJob::create([
            'prospect_id' => $prospect->id,
            'job_type' => 'accessibility',
            'status' => 'failed',
            'error_message' => 'page.goto: Timeout',
            'completed_at' => now(),
        ]);
        AuditJobErrorDetail::create([
            'audit_job_id' => $job->id,
            'body' => "page.goto: Timeout\nCall log:\n  - navigating",
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->get("/prospects/{$prospect->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Prospect/Show')
                ->has('auditFailure')
                ->where('auditFailure.summary', 'page.goto: Timeout')
                ->where('auditFailure.detail_expired', false)
                ->where('auditFailure.full', "page.goto: Timeout\nCall log:\n  - navigating"));
    }

    public function test_show_marks_audit_failure_detail_expired_when_purged(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'audit_status' => 'failed',
        ]);
        AuditJob::create([
            'prospect_id' => $prospect->id,
            'job_type' => 'accessibility',
            'status' => 'failed',
            'error_message' => 'Audit script failed',
            'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get("/prospects/{$prospect->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('auditFailure.summary', 'Audit script failed')
                ->where('auditFailure.detail_expired', true)
                ->where('auditFailure.full', null));
    }

    public function test_show_omits_audit_failure_when_not_failed(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'audit_status' => 'complete',
        ]);

        $this->actingAs($user)
            ->get("/prospects/{$prospect->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('auditFailure', null));
    }

    public function test_show_omits_audit_when_pending(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id, 'scan_type' => 'combined']);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'audit_status' => 'pending',
            'website_url' => 'https://example.com',
            'raw_a11y_payload' => null,
        ]);

        $this->actingAs($user)
            ->get("/prospects/{$prospect->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Prospect/Show')
                ->where('audit', null)
                ->where('lighthouse', null)
                ->where('progress_flow.audit_status', 'pending')
                ->where('progress_flow.status_message', 'Running accessibility audit'));
    }

    public function test_show_navigation_defaults_to_search(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id, 'niche' => 'plumbers']);
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);

        $this->actingAs($user)
            ->get("/prospects/{$prospect->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Prospect/Show')
                ->where('navigation.back_href', "/searches/{$search->id}")
                ->where('navigation.back_label', 'Back to plumbers'));
    }

    public function test_show_navigation_from_outreach_queue(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
        ]);

        $this->actingAs($user)
            ->get("/prospects/{$prospect->id}?from=outreach")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Prospect/Show')
                ->where('navigation.back_href', '/outreach')
                ->where('navigation.back_label', 'Back to outreach'));
    }

    public function test_show_direct_url_search_uses_single_site_context(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->directUrl('https://wheretoescape.com/en-gb/')->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);

        $this->actingAs($user)
            ->get("/prospects/{$prospect->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Prospect/Show')
                ->where('search.source', 'direct_url')
                ->where('search.submitted_url', 'https://wheretoescape.com/en-gb/')
                ->where('navigation.back_label', 'Back to single site'));
    }

    public function test_show_includes_lighthouse_from_performance_score_when_payload_missing(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'audit_status' => 'complete',
            'performance_score' => 20,
            'raw_a11y_payload' => null,
            'raw_lighthouse_payload' => null,
        ]);

        $this->actingAs($user)
            ->get("/prospects/{$prospect->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Prospect/Show')
                ->where('lighthouse.performance', 20)
                ->where('lighthouse.accessibility', null));
    }

    public function test_show_includes_page_speed_when_lighthouse_detail_present(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id, 'scan_type' => 'combined'])->id,
            'audit_status' => 'complete',
            'website_url' => 'https://example.com',
            'performance_score' => 28,
            'raw_a11y_payload' => ['url' => 'https://example.com', 'violations' => []],
            'raw_lighthouse_payload' => [
                'performance' => 28,
                'metrics' => [
                    'lcp' => ['display' => '3.2 s', 'rating' => 'poor'],
                ],
                'opportunities' => [
                    [
                        'id' => 'unused-javascript',
                        'title' => 'Reduce unused JavaScript',
                        'description' => 'Remove unused JavaScript.',
                        'savings_ms' => 1200,
                        'savings_display' => 'Est. savings 1.2 s',
                    ],
                ],
            ],
        ]);

        $this->actingAs($user)
            ->get("/prospects/{$prospect->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Prospect/Show')
                ->has('pageSpeed')
                ->where('pageSpeed.metrics.lcp.display', '3.2 s')
                ->where('pageSpeed.opportunities.0.highlight', true));
    }

    public function test_show_omits_page_speed_for_legacy_lighthouse_payload(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id, 'scan_type' => 'combined'])->id,
            'audit_status' => 'complete',
            'performance_score' => 28,
            'raw_lighthouse_payload' => ['performance' => 28, 'accessibility' => 60],
        ]);

        $this->actingAs($user)
            ->get("/prospects/{$prospect->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Prospect/Show')
                ->where('pageSpeed', null));
    }

    public function test_show_includes_market_scan_for_area_search(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'niche' => 'Dental Clinic',
            'city' => 'Leeds',
        ]);
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);

        NicheScan::query()->create([
            'niche' => 'Dental Clinic',
            'niche_query' => 'dental clinic',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => now('Europe/London')->subDay()->toDateString(),
            'result_count' => 12,
            'sampled_count' => 5,
            'opportunity_score' => 44.5,
            'status' => NicheScanStatus::Complete,
            'ran_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->get("/prospects/{$prospect->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Prospect/Show')
                ->has('marketScan')
                ->where('marketScan.niche', 'Dental Clinic')
                ->where('marketScan.city', 'Leeds')
                ->where('marketScan.opportunity_score', 44.5)
                ->where('marketScan.result_count', 12)
                ->where('marketScan.status', 'complete')
                ->where('marketScan.is_pending', false)
                ->where('marketScan.niches_url', '/niches?city=Leeds'));
    }

    public function test_show_omits_market_scan_for_direct_url_search(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->directUrl()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);

        $this->actingAs($user)
            ->get("/prospects/{$prospect->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Prospect/Show')
                ->where('marketScan', null));
    }

    public function test_show_includes_list_membership_and_addable_lists(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);

        $onList = ProspectList::create([
            'user_id' => $user->id,
            'name' => 'Active pipeline',
            'type' => ProspectListType::Manual,
        ]);
        $openList = ProspectList::create([
            'user_id' => $user->id,
            'name' => 'Future follow-up',
            'type' => ProspectListType::Manual,
        ]);

        ProspectListItem::create([
            'prospect_list_id' => $onList->id,
            'prospect_id' => $prospect->id,
            'status' => ListItemStatus::Replied,
        ]);

        $this->actingAs($user)
            ->get("/prospects/{$prospect->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Prospect/Show')
                ->has('listMembership', 1)
                ->where('listMembership.0.list_id', $onList->id)
                ->where('listMembership.0.list_name', 'Active pipeline')
                ->where('listMembership.0.status', 'replied')
                ->where('listMembership.0.status_label', 'Replied')
                ->has('addableLists', 1)
                ->where('addableLists.0.id', $openList->id)
                ->where('addableLists.0.name', 'Future follow-up'));
    }

    public function test_show_includes_in_outreach_false_by_default(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
        ]);

        $this->actingAs($user)
            ->get("/prospects/{$prospect->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Prospect/Show')
                ->where('inOutreach', false));
    }

    public function test_show_includes_in_outreach_when_queued(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
        ]);

        OutreachSelection::create([
            'user_id' => $user->id,
            'prospect_id' => $prospect->id,
        ]);

        $this->actingAs($user)
            ->get("/prospects/{$prospect->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Prospect/Show')
                ->where('inOutreach', true));
    }
}
