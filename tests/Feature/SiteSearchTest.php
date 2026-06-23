<?php

namespace Tests\Feature;

use App\Enums\IgnoredProspectReason;
use App\Enums\ProspectListType;
use App\Enums\ReportBookingStatus;
use App\Models\IgnoredProspect;
use App\Models\Prospect;
use App\Models\ProspectList;
use App\Models\ProspectNote;
use App\Models\ProspectReport;
use App\Models\ReportBooking;
use App\Models\Search;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SiteSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_requires_auth(): void
    {
        $this->get('/find?q=acme')->assertRedirect('/login');
    }

    public function test_query_too_short_shows_prompt_without_results(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->for($user)->create();
        Prospect::factory()->create([
            'search_id' => $search->id,
            'business_name' => 'Acme Dental',
        ]);

        $this->actingAs($user)
            ->get('/find?q=a')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Find/Index')
                ->where('status', 'too_short')
                ->where('sections', []));
    }

    public function test_finds_prospect_by_business_name(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->for($user)->create();
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'business_name' => 'Acme Dental Studio',
            'website_url' => 'https://acme.example',
        ]);

        $this->actingAs($user)
            ->get('/find?q=acme')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Find/Index')
                ->where('status', 'results')
                ->where('query', 'acme')
                ->has('sections', 1)
                ->where('sections.0.key', 'prospects')
                ->where('sections.0.items.0.title', 'Acme Dental Studio')
                ->where('sections.0.items.0.href', route('prospects.show', $prospect)));
    }

    public function test_ignored_prospect_is_excluded_from_prospect_results(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->for($user)->create();
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'business_name' => 'Hidden Acme Co',
            'place_id' => 'places/hidden-acme',
        ]);

        IgnoredProspect::create([
            'user_id' => $user->id,
            'place_id' => $prospect->place_id,
            'reason' => IgnoredProspectReason::Other,
        ]);

        $this->actingAs($user)
            ->get('/find?q=hidden%20acme')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('status', 'empty')
                ->where('sections', []));
    }

    public function test_finds_scan_by_niche(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->for($user)->create([
            'niche' => 'Orthodontist',
            'city' => 'Leeds',
        ]);

        $this->actingAs($user)
            ->get('/find?q=orthodontist')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('status', 'results')
                ->has('sections', 1)
                ->where('sections.0.key', 'scans')
                ->where('sections.0.items.0.href', route('searches.show', $search)));
    }

    public function test_cross_tenant_isolation(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $search = Search::factory()->for($owner)->create();
        Prospect::factory()->create([
            'search_id' => $search->id,
            'business_name' => 'Secret Tenant Co',
        ]);

        $this->actingAs($other)
            ->get('/find?q=secret%20tenant')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('status', 'empty')
                ->where('sections', []));
    }

    public function test_multi_token_query_requires_all_tokens(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->for($user)->create(['city' => 'London']);
        Prospect::factory()->create([
            'search_id' => $search->id,
            'business_name' => 'Smith Dental',
            'address' => '12 High Street, London',
        ]);
        Prospect::factory()->create([
            'search_id' => Search::factory()->for($user)->create(['city' => 'Manchester'])->id,
            'business_name' => 'Smith Ortho',
        ]);

        $this->actingAs($user)
            ->get('/find?q=smith%20london')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('status', 'results')
                ->has('sections', 1)
                ->where('sections.0.key', 'prospects')
                ->where('sections.0.items', fn ($items) => count($items) === 1
                    && $items[0]['title'] === 'Smith Dental'));
    }

    public function test_tag_result_includes_browse_url(): void
    {
        $user = User::factory()->create();
        Tag::create([
            'user_id' => $user->id,
            'name' => 'hot-lead',
            'color' => '#111111',
        ]);

        $this->actingAs($user)
            ->get('/find?q=hot-lead')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('status', 'results')
                ->has('sections', 1)
                ->where('sections.0.key', 'tags')
                ->where('sections.0.items.0.href', route('lists.browse', ['tags' => ['hot-lead']])));
    }

    public function test_finds_note_by_body(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->for($user)->create();
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'business_name' => 'Note Target Ltd',
        ]);

        ProspectNote::create([
            'prospect_id' => $prospect->id,
            'user_id' => $user->id,
            'body' => 'Follow up about rebranding project',
        ]);

        $this->actingAs($user)
            ->get('/find?q=rebranding')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('status', 'results')
                ->has('sections', 1)
                ->where('sections.0.key', 'notes')
                ->where('sections.0.items.0.href', route('prospects.show', $prospect)));
    }

    public function test_finds_list_by_name(): void
    {
        $user = User::factory()->create();
        $list = ProspectList::create([
            'user_id' => $user->id,
            'name' => 'Priority Outreach',
            'type' => ProspectListType::Manual,
        ]);

        $this->actingAs($user)
            ->get('/find?q=priority%20outreach')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('status', 'results')
                ->has('sections', 1)
                ->where('sections.0.key', 'lists')
                ->where('sections.0.items.0.href', route('lists.show', $list)));
    }

    public function test_like_wildcards_are_treated_literally(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->for($user)->create();
        Prospect::factory()->create([
            'search_id' => $search->id,
            'business_name' => '100% Pure Dental',
        ]);

        $this->actingAs($user)
            ->get('/find?q='.urlencode('100%'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('status', 'results')
                ->where('sections.0.key', 'prospects'));

        Prospect::factory()->create([
            'search_id' => $search->id,
            'business_name' => 'Wildcard Chaos Ltd',
        ]);

        $this->actingAs($user)
            ->get('/find?q=chaos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('status', 'results')
                ->where('sections.0.items.0.title', 'Wildcard Chaos Ltd'));
    }

    public function test_finds_report_by_prospect_name(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->for($user)->create();
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'business_name' => 'Reportable Widgets',
        ]);
        ProspectReport::factory()->create([
            'prospect_id' => $prospect->id,
            'view_count' => 3,
        ]);

        $this->actingAs($user)
            ->get('/find?q=reportable')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('status', 'results')
                ->where('sections', fn ($sections) => collect($sections)->contains(
                    fn ($section) => $section['key'] === 'reports'
                        && $section['items'][0]['href'] === route('prospects.show', $prospect),
                )));
    }

    public function test_finds_booking_by_attendee_name(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->for($user)->create();
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'business_name' => 'Booked Business',
        ]);
        $report = ProspectReport::factory()->create(['prospect_id' => $prospect->id]);

        ReportBooking::query()->create([
            'prospect_report_id' => $report->id,
            'prospect_id' => $prospect->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'attendee_name' => 'Jordan Patel',
            'attendee_email' => 'jordan@example.com',
            'status' => ReportBookingStatus::Confirmed,
        ]);

        $this->actingAs($user)
            ->get('/find?q=jordan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('status', 'results')
                ->has('sections', 1)
                ->where('sections.0.key', 'bookings')
                ->where('sections.0.items.0.href', route('bookings.index')));
    }
}
