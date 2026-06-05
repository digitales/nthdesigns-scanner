<?php

namespace Tests\Feature;

use App\Models\AuditJob;
use App\Models\Prospect;
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

    public function test_user_cannot_view_another_users_search(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $search = Search::factory()->for($owner)->create();

        $this->actingAs($other)
            ->get(route('searches.show', $search))
            ->assertForbidden();
    }
}
