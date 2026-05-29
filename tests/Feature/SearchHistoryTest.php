<?php

namespace Tests\Feature;

use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SearchHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_history_requires_auth(): void
    {
        $this->get('/searches')->assertRedirect('/login');
    }

    public function test_user_sees_only_their_searches(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $mine = Search::factory()->for($user)->create(['niche' => 'Mine']);
        Search::factory()->for($other)->create(['niche' => 'Theirs']);

        $this->actingAs($user)
            ->get('/searches')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search/History')
                ->has('searches', 1)
                ->where('searches.0.id', $mine->id)
                ->where('searches.0.niche', 'Mine'));
    }

    public function test_search_history_paginates_at_twenty_per_page(): void
    {
        $user = User::factory()->create();
        Search::factory()->count(25)->for($user)->create();

        $this->actingAs($user)
            ->get('/searches')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('searches', 20)
                ->where('pagination.total', 25)
                ->where('pagination.current_page', 1)
                ->where('pagination.last_page', 2));

        $this->actingAs($user)
            ->get('/searches?page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('searches', 5)
                ->where('pagination.current_page', 2));
    }

    public function test_search_index_includes_recent_searches(): void
    {
        $user = User::factory()->create();
        Search::factory()->count(3)->for($user)->create();

        $this->actingAs($user)
            ->get('/search')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search/Index')
                ->has('recentSearches', 3));
    }
}
