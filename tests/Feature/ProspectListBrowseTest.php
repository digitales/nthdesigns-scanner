<?php

namespace Tests\Feature;

use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProspectListBrowseTest extends TestCase
{
    use RefreshDatabase;

    public function test_browse_requires_auth(): void
    {
        $this->get('/lists/browse')->assertRedirect('/login');
    }

    public function test_browse_paginates_at_twenty_per_page(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->for($user)->create();
        Prospect::factory()->count(25)->create(['search_id' => $search->id]);

        $this->actingAs($user)
            ->get('/lists/browse')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Lists/Browse')
                ->has('prospects', 20)
                ->where('meta.total', 25)
                ->where('pagination.total', 25)
                ->where('pagination.current_page', 1)
                ->where('pagination.last_page', 2));

        $this->actingAs($user)
            ->get('/lists/browse?page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('prospects', 5)
                ->where('pagination.current_page', 2));
    }

    public function test_browse_preserves_filters_in_pagination(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->for($user)->create(['niche' => 'Dental']);
        Prospect::factory()->count(25)->create(['search_id' => $search->id]);

        $this->actingAs($user)
            ->get('/lists/browse?niche=Dental&page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.niche', 'Dental')
                ->where('pagination.current_page', 2));
    }
}
