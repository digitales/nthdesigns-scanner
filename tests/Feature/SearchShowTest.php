<?php

namespace Tests\Feature;

use App\Enums\IgnoredProspectReason;
use App\Enums\ListItemStatus;
use App\Enums\ProspectListType;
use App\Models\AuditJob;
use App\Models\IgnoredProspect;
use App\Models\Prospect;
use App\Models\ProspectList;
use App\Models\ProspectListItem;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SearchShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_show_includes_prospect_payload_shape(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->for($user)->create([
            'niche' => 'Dentist',
            'city' => 'Leeds',
            'status' => 'auditing',
            'total_found' => 1,
        ]);

        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'business_name' => 'Bright Smile',
            'audit_status' => 'failed',
            'combined_score' => 72,
        ]);

        AuditJob::create([
            'prospect_id' => $prospect->id,
            'job_type' => 'accessibility',
            'status' => 'failed',
            'error_message' => 'timeout',
            'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('searches.show', $search))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search/Show')
                ->where('search.id', $search->id)
                ->where('search.niche', 'Dentist')
                ->has('search.progress_flow')
                ->has('prospects', 1)
                ->where('prospects.0.business_name', 'Bright Smile')
                ->where('prospects.0.audit_status', 'failed')
                ->where('prospects.0.audit_error', 'timeout')
                ->where('prospects.0.combined_score', 72)
                ->has('prospects.0.progress_flow'));
    }

    public function test_search_show_includes_list_memberships_and_manual_lists(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->for($user)->create([
            'niche' => 'Dentist',
            'city' => 'Leeds',
        ]);

        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'business_name' => 'Bright Smile',
        ]);

        $list = ProspectList::create([
            'user_id' => $user->id,
            'name' => 'Q2 dental',
            'type' => ProspectListType::Manual,
        ]);
        $emptyList = ProspectList::create([
            'user_id' => $user->id,
            'name' => 'Later',
            'type' => ProspectListType::Manual,
        ]);

        ProspectListItem::create([
            'prospect_list_id' => $list->id,
            'prospect_id' => $prospect->id,
            'status' => ListItemStatus::New,
        ]);

        $this->actingAs($user)
            ->get(route('searches.show', $search))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search/Show')
                ->has('manualLists', 2)
                ->has('prospects.0.list_memberships', 1)
                ->where('prospects.0.list_memberships.0.list_name', 'Q2 dental')
                ->where('prospects.0.list_memberships.0.status_label', 'New')
                ->where('manualLists.0.name', 'Later')
                ->where('manualLists.1.name', 'Q2 dental'));
    }

    public function test_user_cannot_view_another_users_search(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $search = Search::factory()->for($owner)->create();

        $this->actingAs($other)
            ->get(route('searches.show', $search))
            ->assertForbidden();
    }

    public function test_search_show_sorts_reviewed_prospects_to_bottom(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->for($user)->create([
            'niche' => 'Dentist',
            'city' => 'Leeds',
        ]);

        Prospect::factory()->create([
            'search_id' => $search->id,
            'business_name' => 'Bright Smile',
            'place_id' => 'places/visible',
            'combined_score' => 90,
        ]);
        $hidden = Prospect::factory()->create([
            'search_id' => $search->id,
            'business_name' => 'Hidden Dental',
            'place_id' => 'places/hidden',
            'combined_score' => 95,
        ]);

        IgnoredProspect::create([
            'user_id' => $user->id,
            'place_id' => $hidden->place_id,
            'reason' => IgnoredProspectReason::Reviewed,
        ]);

        $this->actingAs($user)
            ->get(route('searches.show', $search))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search/Show')
                ->where('hiddenCount', 1)
                ->has('prospects', 2)
                ->where('prospects.0.business_name', 'Bright Smile')
                ->where('prospects.0.is_hidden', false)
                ->where('prospects.1.business_name', 'Hidden Dental')
                ->where('prospects.1.is_hidden', true));
    }

    public function test_user_can_bulk_hide_prospects_from_search(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->for($user)->create();

        $first = Prospect::factory()->create(['search_id' => $search->id]);
        $second = Prospect::factory()->create(['search_id' => $search->id]);

        $this->actingAs($user)
            ->post(route('searches.bulk-hide', $search), [
                'prospect_ids' => [$first->id, $second->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('ignored_prospects', [
            'user_id' => $user->id,
            'place_id' => $first->place_id,
            'reason' => IgnoredProspectReason::Reviewed->value,
        ]);
        $this->assertDatabaseHas('ignored_prospects', [
            'user_id' => $user->id,
            'place_id' => $second->place_id,
            'reason' => IgnoredProspectReason::Reviewed->value,
        ]);
    }
}
