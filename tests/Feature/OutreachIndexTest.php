<?php

namespace Tests\Feature;

use App\Models\OutreachEmail;
use App\Models\OutreachSelection;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\ReportBooking;
use App\Models\Search;
use App\Models\User;
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
        ]);
        $report = ProspectReport::factory()->create(['prospect_id' => $prospect->id]);
        ReportBooking::query()->create([
            'prospect_report_id' => $report->id,
            'prospect_id' => $prospect->id,
            'starts_at' => now()->addDays(2)->setTime(14, 30),
            'ends_at' => now()->addDays(2)->setTime(15, 0),
            'attendee_name' => 'Jane Smith',
            'attendee_email' => 'jane@example.com',
            'status' => 'confirmed',
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
}
