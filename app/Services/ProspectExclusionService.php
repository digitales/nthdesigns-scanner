<?php

namespace App\Services;

use App\Enums\IgnoredProspectReason;
use App\Models\IgnoredProspect;
use App\Models\Prospect;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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

    public function ignore(User $user, Prospect $prospect, IgnoredProspectReason|string $reason, ?string $note = null): IgnoredProspect
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
     * @return Collection<int, array{
     *     id: int,
     *     place_id: string,
     *     reason: string,
     *     reason_label: string,
     *     note: string|null,
     *     ignored_at: string,
     *     prospect_id: int|null,
     *     business_name: string|null,
     *     niche: string|null,
     *     city: string|null,
     *     combined_score: int|null
     * }>
     */
    public function listForUser(User $user, ?string $reason = null): Collection
    {
        $ignored = $this->ignoredQueryForUser($user, $reason)->get();

        if ($ignored->isEmpty()) {
            return collect();
        }

        $latestByPlace = $this->latestProspectsByPlace($user, $ignored->pluck('place_id')->all());

        return $ignored->map(fn (IgnoredProspect $row) => $this->formatIgnoredRow($row, $latestByPlace));
    }

    public function paginateForUser(User $user, ?string $reason = null, int $perPage = 20): LengthAwarePaginator
    {
        $paginator = $this->ignoredQueryForUser($user, $reason)
            ->paginate($perPage)
            ->withQueryString();

        $latestByPlace = $this->latestProspectsByPlace(
            $user,
            $paginator->getCollection()->pluck('place_id')->all(),
        );

        return $paginator->through(fn (IgnoredProspect $row) => $this->formatIgnoredRow($row, $latestByPlace));
    }

    /**
     * @return Builder<IgnoredProspect>
     */
    private function ignoredQueryForUser(User $user, ?string $reason = null)
    {
        $query = IgnoredProspect::query()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at');

        if ($reason !== null && $reason !== '') {
            $query->where('reason', $reason);
        }

        return $query;
    }

    /**
     * @param  list<string>  $placeIds
     * @return Collection<string, Prospect>
     */
    private function latestProspectsByPlace(User $user, array $placeIds): Collection
    {
        if ($placeIds === []) {
            return collect();
        }

        return Prospect::query()
            ->whereIn('place_id', $placeIds)
            ->whereHas('search', fn ($q) => $q->where('user_id', $user->id))
            ->with('search')
            ->orderByDesc('id')
            ->get()
            ->groupBy('place_id')
            ->map->first();
    }

    /**
     * @param  Collection<string, Prospect>  $latestByPlace
     * @return array{
     *     id: int,
     *     place_id: string,
     *     reason: string,
     *     reason_label: string,
     *     note: string|null,
     *     ignored_at: string,
     *     prospect_id: int|null,
     *     business_name: string|null,
     *     niche: string|null,
     *     city: string|null,
     *     combined_score: int|null
     * }
     */
    private function formatIgnoredRow(IgnoredProspect $row, Collection $latestByPlace): array
    {
        $prospect = $latestByPlace->get($row->place_id);

        return [
            'id' => $row->id,
            'place_id' => $row->place_id,
            'reason' => $row->reason->value,
            'reason_label' => $row->label(),
            'note' => $row->note,
            'ignored_at' => $row->updated_at->diffForHumans(),
            'prospect_id' => $prospect?->id,
            'business_name' => $prospect?->business_name,
            'niche' => $prospect?->search?->niche,
            'city' => $prospect?->search?->city,
            'combined_score' => $prospect?->combined_score,
        ];
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
