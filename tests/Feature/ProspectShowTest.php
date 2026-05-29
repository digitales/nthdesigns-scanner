<?php

namespace Tests\Feature;

use App\Models\AuditJob;
use App\Models\Prospect;
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

    public function test_show_omits_audit_when_pending(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'audit_status' => 'pending',
            'raw_a11y_payload' => null,
        ]);

        $this->actingAs($user)
            ->get("/prospects/{$prospect->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Prospect/Show')
                ->where('audit', null)
                ->where('lighthouse', null));
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
}
