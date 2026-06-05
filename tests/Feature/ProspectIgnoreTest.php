<?php

namespace Tests\Feature;

use App\Jobs\ScrapeProspectsJob;
use App\Jobs\ScorePlaceJob;
use App\Models\IgnoredProspect;
use App\Models\OutreachSelection;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Services\GooglePlacesService;
use App\Services\SearchStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProspectIgnoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_ignore_prospect_with_reason_and_note(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'place_id'  => 'places/stackhouse',
        ]);

        $this->actingAs($user)
            ->post("/prospects/{$prospect->id}/ignore", [
                'reason' => IgnoredProspect::REASON_ACQUIRED,
                'note'   => 'Acquired by Gallagher',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('ignored_prospects', [
            'user_id'  => $user->id,
            'place_id' => 'places/stackhouse',
            'reason'   => IgnoredProspect::REASON_ACQUIRED,
            'note'     => 'Acquired by Gallagher',
        ]);
    }

    public function test_ignore_removes_prospect_from_outreach_selections(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'place_id'  => 'places/foo',
        ]);

        OutreachSelection::create([
            'user_id'     => $user->id,
            'prospect_id' => $prospect->id,
        ]);

        $this->actingAs($user)
            ->post("/prospects/{$prospect->id}/ignore", [
                'reason' => IgnoredProspect::REASON_COLD,
            ]);

        $this->assertDatabaseMissing('outreach_selections', [
            'user_id'     => $user->id,
            'prospect_id' => $prospect->id,
        ]);
    }

    public function test_operator_can_undo_ignore(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'place_id'  => 'places/foo',
        ]);

        IgnoredProspect::create([
            'user_id'  => $user->id,
            'place_id' => 'places/foo',
            'reason'   => IgnoredProspect::REASON_COLD,
        ]);

        $this->actingAs($user)
            ->delete("/prospects/{$prospect->id}/ignore")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('ignored_prospects', [
            'user_id'  => $user->id,
            'place_id' => 'places/foo',
        ]);
    }

    public function test_show_includes_ignored_state(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'place_id'  => 'places/foo',
        ]);

        IgnoredProspect::create([
            'user_id'  => $user->id,
            'place_id' => 'places/foo',
            'reason'   => IgnoredProspect::REASON_ACQUIRED,
            'note'     => 'Merged',
        ]);

        $this->actingAs($user)
            ->get("/prospects/{$prospect->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('ignored.reason', IgnoredProspect::REASON_ACQUIRED)
                ->where('ignored.reason_label', 'Company acquired')
                ->where('ignored.note', 'Merged'));
    }

    public function test_ignored_index_lists_entries_for_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id'     => Search::factory()->create(['user_id' => $user->id, 'niche' => 'Insurance', 'city' => 'Liverpool'])->id,
            'place_id'      => 'places/foo',
            'business_name' => 'Stackhouse Poland',
        ]);

        IgnoredProspect::create([
            'user_id'  => $user->id,
            'place_id' => 'places/foo',
            'reason'   => IgnoredProspect::REASON_ACQUIRED,
            'note'     => 'Acquired',
        ]);

        IgnoredProspect::create([
            'user_id'  => $other->id,
            'place_id' => 'places/bar',
            'reason'   => IgnoredProspect::REASON_COLD,
        ]);

        $this->actingAs($user)
            ->get('/ignored')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Ignored/Index')
                ->has('entries', 1)
                ->where('entries.0.business_name', 'Stackhouse Poland')
                ->where('entries.0.reason_label', 'Company acquired')
                ->where('entries.0.prospect_id', $prospect->id));
    }

    public function test_ignored_index_can_filter_by_reason(): void
    {
        $user = User::factory()->create();

        IgnoredProspect::create([
            'user_id'  => $user->id,
            'place_id' => 'places/a',
            'reason'   => IgnoredProspect::REASON_COLD,
        ]);
        IgnoredProspect::create([
            'user_id'  => $user->id,
            'place_id' => 'places/b',
            'reason'   => IgnoredProspect::REASON_ACQUIRED,
        ]);

        $this->actingAs($user)
            ->get('/ignored?reason=cold')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('entries', 1)->where('entries.0.reason', 'cold'));
    }

    public function test_can_remove_ignore_from_ignored_list_without_prospect(): void
    {
        $user = User::factory()->create();
        $ignored = IgnoredProspect::create([
            'user_id'  => $user->id,
            'place_id' => 'places/orphan',
            'reason'   => IgnoredProspect::REASON_OTHER,
        ]);

        $this->actingAs($user)
            ->delete("/ignored/{$ignored->id}")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('ignored_prospects', ['id' => $ignored->id]);
    }

    public function test_cannot_remove_another_users_ignored_entry(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ignored = IgnoredProspect::create([
            'user_id'  => $owner->id,
            'place_id' => 'places/private',
            'reason'   => IgnoredProspect::REASON_OTHER,
        ]);

        $this->actingAs($other)
            ->delete("/ignored/{$ignored->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('ignored_prospects', ['id' => $ignored->id]);
    }

    public function test_scrape_job_skips_ignored_place_ids(): void
    {
        config(['services.google_places.key' => 'test-key']);

        Bus::fake([ScorePlaceJob::class]);

        Http::fake([
            'https://places.googleapis.com/v1/places:searchText' => Http::sequence()
                ->push(['places' => [
                    ['id' => 'places/ignored1'],
                    ['id' => 'places/keep1'],
                ]], 200)
                ->push(['places' => []], 200),
        ]);

        $user = User::factory()->create();
        $search = Search::factory()->create(['status' => 'pending', 'user_id' => $user->id]);

        IgnoredProspect::create([
            'user_id'  => $user->id,
            'place_id' => 'places/ignored1',
            'reason'   => IgnoredProspect::REASON_ACQUIRED,
        ]);

        (new ScrapeProspectsJob($search))->handle(
            app(GooglePlacesService::class),
            app(SearchStatusService::class),
            app(\App\Services\ProspectExclusionService::class),
        );

        $search->refresh();
        $this->assertSame(1, $search->total_found);
        Bus::assertDispatched(ScorePlaceJob::class, fn (ScorePlaceJob $job) => $job->placeId === 'places/keep1');
        Bus::assertNotDispatched(ScorePlaceJob::class, fn (ScorePlaceJob $job) => $job->placeId === 'places/ignored1');
    }
}
