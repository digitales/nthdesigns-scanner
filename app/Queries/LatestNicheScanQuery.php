<?php

namespace App\Queries;

use App\Models\NicheScan;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class LatestNicheScanQuery
{
    /**
     * Latest completed scan row per niche+city (window rank = 1).
     */
    public static function ranked(Closure|null $filter = null): Builder
    {
        $inner = NicheScan::query()
            ->select('*')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY niche, city ORDER BY ran_at DESC, id DESC) AS row_num');

        if ($filter !== null) {
            $filter($inner);
        }

        return NicheScan::query()
            ->fromSub($inner, 'ranked')
            ->where('row_num', 1);
    }

    /**
     * @return Collection<int, int>
     */
    public static function ids(Closure|null $filter = null): Collection
    {
        return self::ranked($filter)->pluck('id');
    }
}
