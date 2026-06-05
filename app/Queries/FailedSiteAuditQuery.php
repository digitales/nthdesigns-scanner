<?php

namespace App\Queries;

use App\Models\AuditJob;
use App\Support\RepairAuditScope;
use App\Models\Prospect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class FailedSiteAuditQuery
{
    public static function query(array $excludeProspectIds = []): Builder
    {
        if (RepairAuditScope::siteAuditsDisabledByDriver()) {
            return Prospect::query()->whereRaw('0 = 1');
        }

        $query = RepairAuditScope::applySiteAuditProspectScope(Prospect::query())
            ->where('audit_status', 'failed')
            ->orderBy('id');

        if ($excludeProspectIds !== []) {
            $query->whereNotIn('id', $excludeProspectIds);
        }

        return $query;
    }

    public static function ids(array $excludeProspectIds = []): array
    {
        return self::query($excludeProspectIds)->pluck('id')->all();
    }

    public static function get(
        ?int $searchId,
        ?int $prospectId,
        ?int $limit,
        array $excludeProspectIds = [],
    ): Collection {
        $query = RepairAuditScope::applySearchProspectFilters(
            self::query($excludeProspectIds),
            $searchId,
            $prospectId,
        );

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public static function reasonFor(Prospect $prospect): string
    {
        $reason = 'audit_status failed';

        $latest = AuditJob::query()
            ->where('prospect_id', $prospect->id)
            ->where('job_type', 'accessibility')
            ->where('status', 'failed')
            ->latest('id')
            ->value('error_message');

        if ($latest) {
            $reason .= ': '.$latest;
        }

        return $reason;
    }
}
