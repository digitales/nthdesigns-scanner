<?php

namespace App\Services;

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

        if (! $search || $search->status === 'complete' || $search->status === 'failed') {
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

        $pendingAudits = (int) ($statusCounts['pending'] ?? 0);

        if ($pendingAudits > 0) {
            if ($search->status !== 'auditing') {
                $search->update(['status' => 'auditing']);
            }

            return;
        }

        $finishedCount = (int) ($statusCounts['complete'] ?? 0)
            + (int) ($statusCounts['skipped'] ?? 0)
            + (int) ($statusCounts['failed'] ?? 0);

        if ($finishedCount >= $totalFound) {
            $search->update(['status' => 'complete']);
        }
    }
}
