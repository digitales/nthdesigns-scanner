<?php

namespace Tests\Unit;

use App\Enums\ListItemStatus;
use App\Enums\ProspectListType;
use App\Models\Prospect;
use App\Models\ProspectList;
use App\Models\ProspectListItem;
use App\Models\Search;
use App\Models\User;
use App\Services\ProspectListMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProspectListMembershipServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_addable_lists_excludes_current_memberships(): void
    {
        $service = app(ProspectListMembershipService::class);
        $manualLists = [
            ['id' => 1, 'name' => 'Alpha'],
            ['id' => 2, 'name' => 'Beta'],
        ];

        $addable = $service->addableLists($manualLists, [
            ['list_id' => 1, 'list_name' => 'Alpha', 'status' => 'new', 'status_label' => 'New'],
        ]);

        $this->assertSame([['id' => 2, 'name' => 'Beta']], $addable);
    }

    public function test_memberships_by_prospect_id_groups_manual_lists_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);

        $manual = ProspectList::create([
            'user_id' => $user->id,
            'name' => 'Mine',
            'type' => ProspectListType::Manual,
        ]);
        $smart = ProspectList::create([
            'user_id' => $user->id,
            'name' => 'Smart',
            'type' => ProspectListType::Smart,
            'filter' => [],
        ]);
        $foreign = ProspectList::create([
            'user_id' => $other->id,
            'name' => 'Theirs',
            'type' => ProspectListType::Manual,
        ]);

        ProspectListItem::create([
            'prospect_list_id' => $manual->id,
            'prospect_id' => $prospect->id,
            'status' => ListItemStatus::Contacted,
        ]);
        ProspectListItem::create([
            'prospect_list_id' => $smart->id,
            'prospect_id' => $prospect->id,
            'status' => ListItemStatus::New,
        ]);
        ProspectListItem::create([
            'prospect_list_id' => $foreign->id,
            'prospect_id' => $prospect->id,
            'status' => ListItemStatus::New,
        ]);

        $grouped = app(ProspectListMembershipService::class)
            ->membershipsByProspectId($user, collect([$prospect->id]));

        $this->assertCount(1, $grouped[$prospect->id]);
        $this->assertSame('Mine', $grouped[$prospect->id][0]['list_name']);
        $this->assertSame('contacted', $grouped[$prospect->id][0]['status']);
        $this->assertSame('Contacted', $grouped[$prospect->id][0]['status_label']);
    }
}
