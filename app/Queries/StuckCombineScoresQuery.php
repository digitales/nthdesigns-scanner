<?php

namespace App\Queries;

use App\Enums\AuditStatus;
use App\Models\Prospect;
use App\Support\RepairAuditScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class StuckCombineScoresQuery
{
    public static function query(): Builder
    {
        return RepairAuditScope::applySiteAuditProspectScope(Prospect::query())
            ->where('audit_status', AuditStatus::Pending->value)
            ->whereNotNull('raw_a11y_payload')
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
        $payload = $prospect->raw_a11y_payload;

        if (is_array($payload) && ! empty($payload['error'])) {
            return 'site load error pending combine: '.$payload['error'];
        }

        return 'audit payload saved but audit_status still pending';
    }
}
