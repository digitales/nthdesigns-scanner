<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

final class RepairAuditScope
{
    public static function applySiteAuditProspectScope(Builder $query): Builder
    {
        return $query
            ->with('search')
            ->whereHas('search', fn (Builder $q) => $q->whereIn('status', ['auditing', 'complete']))
            ->whereHas('search', fn (Builder $q) => $q->whereIn('scan_type', ['accessibility_only', 'combined']))
            ->whereNotNull('website_url')
            ->where('website_url', '!=', '');
    }

    public static function siteAuditsDisabledByDriver(): bool
    {
        return config('scanner.audit_driver') === 'skip';
    }

    public static function applySearchProspectFilters(
        Builder $query,
        ?int $searchId,
        ?int $prospectId,
    ): Builder {
        if ($searchId !== null) {
            $query->where('search_id', $searchId);
        }

        if ($prospectId !== null) {
            $query->whereKey($prospectId);
        }

        return $query;
    }
}
