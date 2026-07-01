<?php

namespace Tests\Feature;

use App\Models\OutreachEmail;
use App\Models\OutreachSelection;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\ReportBooking;
use App\Models\Search;
use App\Models\User;
use App\Enums\ReportBookingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OutreachPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_returns_only_user_queue_members(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $otherSearch = Search::factory()->create(['user_id' => $other->id]);

        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'business_name' => 'Acme Dental',
            'combined_score' => 80,
        ]);
        ProspectReport::factory()->create(['prospect_id' => $prospect->id]);
        OutreachSelection::create(['user_id' => $user->id, 'prospect_id' => $prospect->id]);

        $otherProspect = Prospect::factory()->create(['search_id' => $otherSearch->id]);
        OutreachSelection::create(['user_id' => $other->id, 'prospect_id' => $otherProspect->id]);

        $this->actingAs($user)
            ->get('/lists/pipeline')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Lists/Pipeline')
                ->has('rows', 1)
                ->where('rows.0.business_name', 'Acme Dental')
                ->where('rows.0.refresh_eligible', true));
    }

    public function test_pipeline_booked_tab_filters_to_booked_prospects(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);

        $booked = Prospect::factory()->create(['search_id' => $search->id, 'business_name' => 'Booked Co']);
        $report = ProspectReport::factory()->create(['prospect_id' => $booked->id]);
        ReportBooking::query()->create([
            'prospect_report_id' => $report->id,
            'prospect_id' => $booked->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'attendee_name' => 'Jane',
            'attendee_email' => 'jane@example.com',
            'status' => ReportBookingStatus::Confirmed,
        ]);
        OutreachSelection::create(['user_id' => $user->id, 'prospect_id' => $booked->id]);

        $open = Prospect::factory()->create(['search_id' => $search->id, 'business_name' => 'Open Co']);
        ProspectReport::factory()->create(['prospect_id' => $open->id]);
        OutreachSelection::create(['user_id' => $user->id, 'prospect_id' => $open->id]);

        $this->actingAs($user)
            ->get('/lists/pipeline?booked=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows', 1)
                ->where('rows.0.business_name', 'Booked Co')
                ->where('filters.booked', true));
    }

    public function test_pipeline_filters_by_niche_and_outreach_status(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id, 'niche' => 'Dentists', 'city' => 'Leeds']);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'combined_score' => 90,
        ]);
        ProspectReport::factory()->create(['prospect_id' => $prospect->id]);
        OutreachSelection::create(['user_id' => $user->id, 'prospect_id' => $prospect->id]);
        OutreachEmail::factory()->create([
            'prospect_id' => $prospect->id,
            'user_id' => $user->id,
            'sent_at' => null,
        ]);

        $otherSearch = Search::factory()->create(['user_id' => $user->id, 'niche' => 'Plumbers', 'city' => 'Leeds']);
        $other = Prospect::factory()->create(['search_id' => $otherSearch->id]);
        ProspectReport::factory()->create(['prospect_id' => $other->id]);
        OutreachSelection::create(['user_id' => $user->id, 'prospect_id' => $other->id]);

        $this->actingAs($user)
            ->get('/lists/pipeline?niche=Dentists&outreach_status=drafted')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows', 1)
                ->where('rows.0.outreach_status', 'drafted'));
    }
}
