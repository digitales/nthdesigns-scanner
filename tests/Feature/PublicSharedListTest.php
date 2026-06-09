<?php

namespace Tests\Feature;

use App\Enums\ListItemStatus;
use App\Enums\ProspectListType;
use App\Models\Prospect;
use App\Models\ProspectList;
use App\Models\ProspectListItem;
use App\Models\Search;
use App\Models\SharedList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicSharedListTest extends TestCase
{
    use RefreshDatabase;

    private function createSharedList(User $user): SharedList
    {
        $list = ProspectList::create([
            'user_id' => $user->id,
            'name' => 'Handoff',
            'type' => ProspectListType::Manual,
        ]);
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'niche' => 'Dental',
            'city' => 'Leeds',
        ]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'business_name' => 'Acme Dental',
            'phone' => '0113 000 0000',
            'website_url' => 'https://acme.example',
            'combined_score' => 72,
        ]);
        ProspectListItem::create([
            'prospect_list_id' => $list->id,
            'prospect_id' => $prospect->id,
            'status' => ListItemStatus::New,
        ]);

        $this->actingAs($user)
            ->post("/lists/{$list->id}/share")
            ->assertRedirect();

        return SharedList::firstOrFail();
    }

    public function test_public_shared_list_renders_snapshot_without_contact_details(): void
    {
        $user = User::factory()->create();
        $shared = $this->createSharedList($user);

        $this->get("/s/{$shared->token}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SharedList/Show')
                ->where('listName', 'Handoff')
                ->has('rows', 1)
                ->where('rows.0.business_name', 'Acme Dental')
                ->missing('rows.0.phone')
                ->missing('rows.0.website_url'));

        $content = $this->get("/s/{$shared->token}")->getContent();
        $this->assertStringNotContainsString('0113 000 0000', $content);
        $this->assertStringNotContainsString('acme.example', $content);
    }

    public function test_revoked_share_returns_not_found(): void
    {
        $user = User::factory()->create();
        $shared = $this->createSharedList($user);

        $this->actingAs($user)
            ->delete("/shared-lists/{$shared->id}")
            ->assertRedirect();

        $this->get("/s/{$shared->token}")->assertNotFound();
    }

    public function test_expired_share_returns_not_found(): void
    {
        $user = User::factory()->create();
        $shared = $this->createSharedList($user);
        $shared->update(['expires_at' => now()->subMinute()]);

        $this->get("/s/{$shared->token}")->assertNotFound();
    }

    public function test_operator_cannot_revoke_another_users_share(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $shared = $this->createSharedList($owner);

        $this->actingAs($other)
            ->delete("/shared-lists/{$shared->id}")
            ->assertForbidden();

        $shared->refresh();
        $this->assertNull($shared->revoked_at);
    }

    public function test_unknown_token_returns_not_found(): void
    {
        $this->get('/s/not-a-real-token')->assertNotFound();
    }
}
