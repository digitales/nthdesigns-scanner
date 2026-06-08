<?php

namespace App\Services;

use App\Enums\ProspectListType;
use App\Models\Prospect;
use App\Models\ProspectList;
use App\Models\ProspectListItem;
use App\Queries\ProspectListQuery;

class SharedListSnapshotBuilder
{

    /**
     * @return array{list_name: string, shared_at: string, rows: list<array<string, mixed>>}
     */
    public function build(ProspectList $list): array
    {
        $rows = $list->type === ProspectListType::Manual
            ? $this->rowsFromManualList($list)
            : $this->rowsFromSmartList($list);

        return [
            'list_name' => $list->name,
            'shared_at' => now()->toISOString(),
            'rows' => $rows,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rowsFromManualList(ProspectList $list): array
    {
        $items = ProspectListItem::query()
            ->where('prospect_list_id', $list->id)
            ->with([
                'prospect.search',
                'prospect.tags',
                'prospect.notes' => fn ($q) => $q->latest()->limit(1),
            ])
            ->get();

        return $items->map(fn (ProspectListItem $item) => $this->formatRow(
            $item->prospect,
            $item->status->value,
            $item->follow_up_at?->toISOString(),
        ))->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rowsFromSmartList(ProspectList $list): array
    {
        $prospects = (new ProspectListQuery($list->user))
            ->apply($list->filter ?? [])
            ->query()
            ->with(['search', 'tags', 'notes' => fn ($q) => $q->latest()->limit(1)])
            ->get();

        return $prospects->map(fn (Prospect $prospect) => $this->formatRow($prospect, null, null))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRow(Prospect $prospect, ?string $status, ?string $followUpAt): array
    {
        $gbpFlags = array_slice($prospect->gbp_flags ?? [], 0, 3);
        $a11yFlags = array_slice($prospect->a11y_flags ?? [], 0, 3);

        return [
            'business_name' => $prospect->business_name,
            'niche' => $prospect->search->niche,
            'city' => $prospect->search->city,
            'combined_score' => $prospect->combined_score,
            'gbp_score' => $prospect->gbp_score,
            'flags' => array_values(array_merge($gbpFlags, $a11yFlags)),
            'tags' => $prospect->tags->pluck('name')->values()->all(),
            'note' => $prospect->notes->first()?->body,
            'status' => $status,
            'follow_up_at' => $followUpAt,
        ];
    }
}
