<?php

namespace App\Support;

use App\Enums\ScanType;
use App\Enums\SearchStatus;
use Illuminate\Database\Eloquent\Builder;

final class RepairAuditScope
{
    public static function applySiteAuditProspectScope(Builder $query): Builder
    {
        return $query
            ->with('search')
            ->whereHas('search', fn (Builder $q) => $q->whereIn('status', [SearchStatus::Auditing->value, SearchStatus::Complete->value]))
            ->whereHas('search', fn (Builder $q) => $q->whereIn('scan_type', [ScanType::AccessibilityOnly->value, ScanType::Combined->value]))
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
