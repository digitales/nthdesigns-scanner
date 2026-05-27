<?php

namespace App\Support;

use App\Models\Prospect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class IncompleteAuditQuery
{
    public static function query(): Builder
    {
        return Prospect::query()
            ->with('search')
            ->whereHas('search', fn (Builder $q) => $q->whereIn('status', ['auditing', 'complete']))
            ->whereHas('search', fn (Builder $q) => $q->whereIn('scan_type', ['accessibility_only', 'combined']))
            ->whereNotNull('website_url')
            ->where('website_url', '!=', '')
            ->whereIn('audit_status', ['complete', 'failed'])
            ->where(function (Builder $q) {
                $q->whereNull('raw_a11y_payload')
                    ->orWhereNull('raw_lighthouse_payload')
                    ->orWhere(function (Builder $q) {
                        $q->where('performance_score', 0)
                            ->whereNull('raw_lighthouse_payload');
                    });
            })
            ->orderBy('id');
    }

    public static function ids(): array
    {
        return self::query()->pluck('id')->all();
    }

    public static function get(?int $searchId = null, ?int $prospectId = null, ?int $limit = null): Collection
    {
        $query = self::query();

        if ($searchId !== null) {
            $query->where('search_id', $searchId);
        }

        if ($prospectId !== null) {
            $query->whereKey($prospectId);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public static function reasonFor(Prospect $prospect): string
    {
        if ($prospect->raw_a11y_payload === null) {
            return 'missing raw_a11y_payload';
        }

        if ($prospect->raw_lighthouse_payload === null) {
            return 'missing raw_lighthouse_payload';
        }

        if ((int) $prospect->performance_score === 0) {
            return 'missing lighthouse performance';
        }

        return 'incomplete audit data';
    }
}
