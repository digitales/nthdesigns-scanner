<?php

namespace App\Queries;

use App\Models\Prospect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class UnvalidatedProspectQuery
{
    public static function query(): Builder
    {
        return Prospect::query()
            ->whereNull('validator_ran_at')
            ->whereNull('validator_override_status')
            ->orderBy('id');
    }

    public static function get(?int $searchId = null, ?int $prospectId = null, ?int $limit = null): Collection
    {
        $query = self::query()
            ->when($searchId !== null, fn (Builder $q) => $q->where('search_id', $searchId))
            ->when($prospectId !== null, fn (Builder $q) => $q->whereKey($prospectId));

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }
}
