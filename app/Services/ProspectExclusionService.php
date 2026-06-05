<?php

namespace App\Services;

use App\Models\IgnoredProspect;
use App\Models\Prospect;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ProspectExclusionService
{
    public function isIgnored(int $userId, string $placeId): bool
    {
        return IgnoredProspect::query()
            ->where('user_id', $userId)
            ->where('place_id', $placeId)
            ->exists();
    }

    public function findForUser(int $userId, string $placeId): ?IgnoredProspect
    {
        return IgnoredProspect::query()
            ->where('user_id', $userId)
            ->where('place_id', $placeId)
            ->first();
    }

    public function ignore(User $user, Prospect $prospect, string $reason, ?string $note = null): IgnoredProspect
    {
        $ignored = IgnoredProspect::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'place_id' => $prospect->place_id,
            ],
            [
                'reason' => $reason,
                'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            ],
        );

        $user->outreachSelections()
            ->where('prospect_id', $prospect->id)
            ->delete();

        return $ignored;
    }

    public function includeInScans(User $user, Prospect $prospect): void
    {
        IgnoredProspect::query()
            ->where('user_id', $user->id)
            ->where('place_id', $prospect->place_id)
            ->delete();
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForUser(User $user, ?string $reason = null, int $perPage = 25): LengthAwarePaginator
    {
        $query = IgnoredProspect::query()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at');

        if ($reason !== null && $reason !== '') {
            $query->where('reason', $reason);
        }

        $paginator = $query->paginate($perPage)->withQueryString();
        $ignored = collect($paginator->items());

        if ($ignored->isEmpty()) {
            return $paginator;
        }

        $latestByPlace = Prospect::query()
            ->whereIn('place_id', $ignored->pluck('place_id'))
            ->whereHas('search', fn ($q) => $q->where('user_id', $user->id))
            ->with('search')
            ->orderByDesc('id')
            ->get()
            ->groupBy('place_id')
            ->map->first();

        return $paginator->through(function (IgnoredProspect $row) use ($latestByPlace) {
            $prospect = $latestByPlace->get($row->place_id);

            return [
                'id' => $row->id,
                'place_id' => $row->place_id,
                'reason' => $row->reason,
                'reason_label' => $row->label(),
                'note' => $row->note,
                'ignored_at' => $row->updated_at->diffForHumans(),
                'prospect_id' => $prospect?->id,
                'business_name' => $prospect?->business_name,
                'niche' => $prospect?->search?->niche,
                'city' => $prospect?->search?->city,
                'combined_score' => $prospect?->combined_score,
            ];
        });
    }

    /**
     * @param  list<string>  $placeIds
     * @return list<string>
     */
    public function filterPlaceIds(int $userId, array $placeIds): array
    {
        if ($placeIds === []) {
            return [];
        }

        $ignored = IgnoredProspect::query()
            ->where('user_id', $userId)
            ->whereIn('place_id', $placeIds)
            ->pluck('place_id')
            ->all();

        if ($ignored === []) {
            return $placeIds;
        }

        $ignoredSet = array_flip($ignored);

        return array_values(array_filter(
            $placeIds,
            fn (string $placeId) => ! isset($ignoredSet[$placeId]),
        ));
    }
}
