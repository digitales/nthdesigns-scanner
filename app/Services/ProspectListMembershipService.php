<?php

namespace App\Services;

use App\Enums\ProspectListType;
use App\Models\ProspectListItem;
use App\Models\User;
use Illuminate\Support\Collection;

class ProspectListMembershipService
{
    /**
     * @return list<array{list_id: int, list_name: string, status: string, status_label: string}>
     */
    public function formatItems(Collection $items): array
    {
        return $items
            ->sortBy(fn (ProspectListItem $item) => $item->list?->name)
            ->values()
            ->map(fn (ProspectListItem $item) => [
                'list_id' => $item->prospect_list_id,
                'list_name' => $item->list->name,
                'status' => $item->status->value,
                'status_label' => $item->status->label(),
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function manualListsFor(User $user): array
    {
        return $user->prospectLists()
            ->where('type', ProspectListType::Manual)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($list) => ['id' => $list->id, 'name' => $list->name])
            ->all();
    }

    /**
     * @param  list<array{list_id: int}>  $memberships
     * @param  list<array{id: int, name: string}>  $manualLists
     * @return list<array{id: int, name: string}>
     */
    public function addableLists(array $manualLists, array $memberships): array
    {
        $memberListIds = collect($memberships)->pluck('list_id')->all();

        return collect($manualLists)
            ->whereNotIn('id', $memberListIds)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, int>  $prospectIds
     * @return array<int, list<array{list_id: int, list_name: string, status: string, status_label: string}>>
     */
    public function membershipsByProspectId(User $user, Collection $prospectIds): array
    {
        if ($prospectIds->isEmpty()) {
            return [];
        }

        $items = ProspectListItem::query()
            ->whereIn('prospect_id', $prospectIds)
            ->whereHas('list', fn ($query) => $query
                ->where('user_id', $user->id)
                ->where('type', ProspectListType::Manual))
            ->with('list:id,name')
            ->get();

        return $items
            ->groupBy('prospect_id')
            ->map(fn (Collection $group) => $this->formatItems($group))
            ->all();
    }

    /**
     * @return list<array{list_id: int, list_name: string, status: string, status_label: string}>
     */
    public function membershipsForProspect(User $user, int $prospectId): array
    {
        $items = ProspectListItem::query()
            ->where('prospect_id', $prospectId)
            ->whereHas('list', fn ($query) => $query
                ->where('user_id', $user->id)
                ->where('type', ProspectListType::Manual))
            ->with('list:id,name')
            ->get();

        return $this->formatItems($items);
    }
}
