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
use Tests\TestCase;

class ProspectListTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_index_requires_auth(): void
    {
        $this->get('/lists')->assertRedirect('/login');
    }

    public function test_operator_can_create_manual_list_and_add_prospect(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);

        $this->actingAs($user)
            ->post('/lists', [
                'name' => 'Q2 dental',
                'type' => ProspectListType::Manual->value,
            ])
            ->assertRedirect();

        $list = ProspectList::first();
        $this->assertNotNull($list);
        $this->assertSame(ProspectListType::Manual, $list->type);

        $this->actingAs($user)
            ->post("/lists/{$list->id}/items", ['prospect_ids' => [$prospect->id]])
            ->assertRedirect();

        $this->assertDatabaseHas('prospect_list_items', [
            'prospect_list_id' => $list->id,
            'prospect_id' => $prospect->id,
            'status' => ListItemStatus::New->value,
        ]);
    }

    public function test_operator_can_update_list_item_status_and_follow_up(): void
    {
        $user = User::factory()->create();
        $list = ProspectList::create([
            'user_id' => $user->id,
            'name' => 'Follow-ups',
            'type' => ProspectListType::Manual,
        ]);
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);
        $item = ProspectListItem::create([
            'prospect_list_id' => $list->id,
            'prospect_id' => $prospect->id,
            'status' => ListItemStatus::New,
        ]);

        $this->actingAs($user)
            ->patch("/lists/{$list->id}/items/{$item->id}", [
                'status' => ListItemStatus::Contacted->value,
                'follow_up_at' => '2026-06-15',
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame(ListItemStatus::Contacted, $item->status);
        $this->assertSame('2026-06-15', $item->follow_up_at->format('Y-m-d'));
    }

    public function test_share_link_excludes_contact_details(): void
    {
        $user = User::factory()->create();
        $list = ProspectList::create([
            'user_id' => $user->id,
            'name' => 'Handoff',
            'type' => ProspectListType::Manual,
        ]);
        $search = Search::factory()->create(['user_id' => $user->id, 'niche' => 'Dental', 'city' => 'Leeds']);
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

        $shared = SharedList::first();
        $this->assertNotNull($shared);

        $response = $this->get("/s/{$shared->token}");
        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringContainsString('Acme Dental', $content);
        $this->assertStringNotContainsString('0113 000 0000', $content);
        $this->assertStringNotContainsString('acme.example', $content);
        $this->assertStringNotContainsString('/r/', $content);
    }

    public function test_saved_redirects_to_lists_browse(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/saved?niche=Dental')
            ->assertRedirect('/lists/browse?niche=Dental');
    }

    public function test_operator_cannot_view_another_users_list(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $list = ProspectList::create([
            'user_id' => $owner->id,
            'name' => 'Private',
            'type' => ProspectListType::Manual,
        ]);

        $this->actingAs($other)
            ->get("/lists/{$list->id}")
            ->assertForbidden();
    }
}
