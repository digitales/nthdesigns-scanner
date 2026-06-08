<?php

namespace App\Services;

use App\Enums\AuditStatus;
use App\Enums\SearchStatus;
use App\Models\Prospect;
use App\Models\Search;

class SearchStatusService
{
    /**
     * Advance search status after prospect scoring or auditing changes.
     */
    public function refresh(Search $search): void
    {
        $search = $search->fresh();

        if (! $search || in_array($search->status, [SearchStatus::Complete, SearchStatus::Failed], true)) {
            return;
        }

        $totalFound = $search->total_found ?? 0;

        if ($totalFound === 0) {
            return;
        }

        $statusCounts = Prospect::query()
            ->where('search_id', $search->id)
            ->selectRaw('audit_status, COUNT(*) as aggregate')
            ->groupBy('audit_status')
            ->pluck('aggregate', 'audit_status');

        $prospectCount = (int) $statusCounts->sum();

        if ($prospectCount < $totalFound) {
            return;
        }

        $pendingAudits = (int) ($statusCounts[AuditStatus::Pending->value] ?? 0);

        if ($pendingAudits > 0) {
            if ($search->status !== SearchStatus::Auditing) {
                $search->update(['status' => SearchStatus::Auditing]);
            }

            return;
        }

        $finishedCount = (int) ($statusCounts[AuditStatus::Complete->value] ?? 0)
            + (int) ($statusCounts[AuditStatus::Skipped->value] ?? 0)
            + (int) ($statusCounts[AuditStatus::Failed->value] ?? 0);

        if ($finishedCount >= $totalFound) {
            $search->update(['status' => SearchStatus::Complete]);
        }
    }
}
