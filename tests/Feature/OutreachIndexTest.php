<?php

namespace Tests\Feature;

use App\Enums\ReportBookingStatus;
use App\Models\OutreachEmail;
use App\Models\OutreachSelection;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\ReportBooking;
use App\Models\Search;
use App\Models\User;
use App\Models\WarmupMailbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OutreachIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_serializes_selection_and_emails_via_resources(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'business_name' => 'Acme Dental',
            'combined_score' => 82,
            'performance_score' => 45,
            'email' => 'owner@example.com',
        ]);
        $report = ProspectReport::factory()->create(['prospect_id' => $prospect->id]);
        ReportBooking::query()->create([
            'prospect_report_id' => $report->id,
            'prospect_id' => $prospect->id,
            'starts_at' => now()->addDays(2)->setTime(14, 30),
            'ends_at' => now()->addDays(2)->setTime(15, 0),
            'attendee_name' => 'Jane Smith',
            'attendee_email' => 'jane@example.com',
            'status' => ReportBookingStatus::Confirmed,
        ]);

        OutreachSelection::create([
            'user_id' => $user->id,
            'prospect_id' => $prospect->id,
        ]);

        OutreachEmail::factory()->create([
            'prospect_id' => $prospect->id,
            'user_id' => $user->id,
            'pitch_angle' => 'gbp',
            'subject_line' => 'Quick question',
            'email_body' => 'Hello there',
        ]);

        $this->actingAs($user)
            ->get('/outreach')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Outreach/Index')
                ->has('selection', 1)
                ->where('selection.0.business_name', 'Acme Dental')
                ->where('selection.0.report_ready', true)
                ->where('selection.0.combined_score', 82)
                ->where('selection.0.performance_score', 45)
                ->has('emailsByProspect.'.$prospect->id, 1)
                ->where('emailsByProspect.'.$prospect->id.'.0.subject_line', 'Quick question')
                ->where('emailsByProspect.'.$prospect->id.'.0.pitch_angle', 'gbp'));
    }

    public function test_index_prefills_cpc_from_search(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'cpc_benchmark' => 8.50,
            'cpc_source' => 'manual',
        ]);
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);
        ProspectReport::factory()->create(['prospect_id' => $prospect->id]);

        OutreachSelection::create([
            'user_id' => $user->id,
            'prospect_id' => $prospect->id,
        ]);

        $this->actingAs($user)
            ->get('/outreach')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('defaults.cpc_benchmark', '8.50')
                ->where('defaults.cpc_from_search', true)
                ->where('defaults.cpc_mixed', false));
    }

    public function test_index_includes_warmup_readiness_when_domain_not_ready(): void
    {
        $user = User::factory()->create();
        WarmupMailbox::factory()->outreach()->create([
            'user_id' => $user->id,
            'email' => 'ross@nthdesign.co.uk',
            'status' => 'warming',
            'warmup_started_at' => now()->subDays(3),
            'warmup_ramp_days' => 14,
        ]);

        $this->actingAs($user)
            ->get('/outreach')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('warmup_readiness.state', 'not_ready')
                ->where('warmup_readiness.warming_email', 'ross@nthdesign.co.uk'));
    }

    public function test_index_includes_ready_warmup_mailbox(): void
    {
        $user = User::factory()->create();
        WarmupMailbox::factory()->outreach()->create([
            'user_id' => $user->id,
            'email' => 'ross@nthdesign.co.uk',
            'status' => 'ready',
        ]);

        $this->actingAs($user)
            ->get('/outreach')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('warmup_readiness.state', 'ready')
                ->where('warmup_readiness.primary_email', 'ross@nthdesign.co.uk'));
    }
}
