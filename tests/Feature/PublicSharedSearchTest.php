<?php

namespace Tests\Feature;

use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use App\Models\SharedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicSharedSearchTest extends TestCase
{
    use RefreshDatabase;

    private function createSharedSearch(User $user): SharedSearch
    {
        $search = Search::factory()->for($user)->create([
            'niche' => 'Dental',
            'city' => 'Leeds',
            'cpc_benchmark' => 6.50,
            'cpc_source' => 'manual',
            'cpc_keywords' => ['private dentist leeds'],
        ]);

        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'business_name' => 'Acme Dental',
            'phone' => '0113 000 0000',
            'address' => '1 High Street, Leeds',
            'website_url' => 'https://acme.example',
            'combined_score' => 72,
            'gbp_score' => 65,
            'a11y_score' => 40,
            'performance_score' => 55,
            'dominant_angle' => 'both',
            'audit_status' => 'complete',
        ]);

        ProspectReport::factory()->create([
            'prospect_id' => $prospect->id,
        ]);

        $this->actingAs($user)
            ->post(route('searches.share', $search))
            ->assertRedirect();

        return SharedSearch::firstOrFail();
    }

    public function test_share_flash_includes_shared_url_on_search_show(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->for($user)->create();
        Prospect::factory()->create(['search_id' => $search->id]);

        $this->actingAs($user)
            ->from(route('searches.show', $search))
            ->post(route('searches.share', $search))
            ->assertRedirect(route('searches.show', $search));

        $this->get(route('searches.show', $search))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search/Show')
                ->where('flash.shared_url', fn ($url) => is_string($url) && str_contains($url, '/q/')));
    }

    public function test_share_creates_public_snapshot_with_expected_fields(): void
    {
        $user = User::factory()->create();
        $shared = $this->createSharedSearch($user);

        $this->get("/q/{$shared->token}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SharedSearch/Show')
                ->where('search.niche', 'Dental')
                ->where('search.city', 'Leeds')
                ->where('search.cpc_benchmark', '6.50')
                ->has('prospects', 1)
                ->where('prospects.0.business_name', 'Acme Dental')
                ->where('prospects.0.website_url', 'https://acme.example')
                ->where('prospects.0.combined_score', 72)
                ->where('prospects.0.report_url', fn ($url) => str_contains($url, '/r/'))
                ->missing('prospects.0.phone')
                ->missing('prospects.0.address')
                ->missing('prospects.0.id'));

        $content = $this->get("/q/{$shared->token}")->getContent();
        $this->assertStringNotContainsString('0113 000 0000', $content);
        $this->assertStringContainsString('acme.example', $content);
    }

    public function test_share_requires_at_least_one_prospect(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('searches.share', $search))
            ->assertSessionHasErrors('search');

        $this->assertDatabaseCount('shared_searches', 0);
    }

    public function test_operator_cannot_share_another_users_search(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $search = Search::factory()->for($owner)->create();
        Prospect::factory()->create(['search_id' => $search->id]);

        $this->actingAs($other)
            ->post(route('searches.share', $search))
            ->assertForbidden();
    }

    public function test_revoked_share_returns_not_found(): void
    {
        $user = User::factory()->create();
        $shared = $this->createSharedSearch($user);

        $this->actingAs($user)
            ->delete(route('shared-searches.destroy', $shared))
            ->assertRedirect();

        $this->get("/q/{$shared->token}")->assertNotFound();
    }

    public function test_expired_share_returns_not_found(): void
    {
        $user = User::factory()->create();
        $shared = $this->createSharedSearch($user);
        $shared->update(['expires_at' => now()->subMinute()]);

        $this->get("/q/{$shared->token}")->assertNotFound();
    }

    public function test_operator_cannot_revoke_another_users_share(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $shared = $this->createSharedSearch($owner);

        $this->actingAs($other)
            ->delete(route('shared-searches.destroy', $shared))
            ->assertForbidden();

        $shared->refresh();
        $this->assertNull($shared->revoked_at);
    }

    public function test_unknown_token_returns_not_found(): void
    {
        $this->get('/q/not-a-real-token')->assertNotFound();
    }
}
