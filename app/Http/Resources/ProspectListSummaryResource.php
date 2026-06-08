<?php

namespace App\Http\Resources;

use App\Enums\ProspectListType;
use App\Models\ProspectList;

class ProspectListSummaryResource
{
    public static function format(ProspectList $list): array
    {
        $prospectCount = $list->type === ProspectListType::Manual
            ? $list->items()->count()
            : null;

        $overdueCount = $list->type === ProspectListType::Manual
            ? $list->items()->where('follow_up_at', '<', now())->count()
            : 0;

        $nextFollowUp = $list->type === ProspectListType::Manual
            ? $list->items()
                ->whereNotNull('follow_up_at')
                ->orderBy('follow_up_at')
                ->value('follow_up_at')
            : null;

        return [
            'id' => $list->id,
            'name' => $list->name,
            'type' => $list->type->value,
            'type_label' => $list->type->label(),
            'description' => $list->description,
            'prospect_count' => $prospectCount,
            'overdue_count' => $overdueCount,
            'next_follow_up_at' => $nextFollowUp?->toISOString(),
            'filter' => $list->filter,
            'updated_at' => $list->updated_at->diffForHumans(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ProspectList>  $lists
     * @return list<array<string, mixed>>
     */
    public static function formatIndex($lists, string $sort = 'updated'): array
    {
        $formatted = $lists->map(fn (ProspectList $list) => self::format($list))->all();

        if ($sort === 'due_soon') {
            usort($formatted, function (array $a, array $b) {
                $aTime = $a['next_follow_up_at'] ?? '9999';
                $bTime = $b['next_follow_up_at'] ?? '9999';

                return $aTime <=> $bTime;
            });
        }

        return $formatted;
    }
}
