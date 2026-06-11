<?php

namespace App\Queries;

use App\Enums\AuditStatus;
use App\Models\Prospect;
use App\Support\RepairAuditScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class StuckReportQuery
{
    public static function query(): Builder
    {
        return RepairAuditScope::applySiteAuditProspectScope(Prospect::query())
            ->whereIn('audit_status', [AuditStatus::Complete->value, AuditStatus::Skipped->value])
            ->whereDoesntHave('report')
            ->orderBy('id');
    }

    public static function ids(): array
    {
        return self::query()->pluck('id')->all();
    }

    public static function get(?int $searchId = null, ?int $prospectId = null, ?int $limit = null): Collection
    {
        $query = RepairAuditScope::applySearchProspectFilters(self::query(), $searchId, $prospectId);

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public static function reasonFor(Prospect $prospect): string
    {
        return 'audit complete without prospect report';
    }
}
