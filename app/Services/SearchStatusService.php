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

        if (!$search || $search->status === 'complete' || $search->status === 'failed') {
            return;
        }

        $totalFound = $search->total_found ?? 0;

        if ($totalFound === 0) {
            return;
        }

        $prospectCount = Prospect::where('search_id', $search->id)->count();

        if ($prospectCount < $totalFound) {
            return;
        }

        $pendingAudits = Prospect::where('search_id', $search->id)
            ->where('audit_status', 'pending')
            ->count();

        if ($pendingAudits > 0) {
            if ($search->status !== 'auditing') {
                $search->update(['status' => 'auditing']);
            }

            return;
        }

        $finishedCount = Prospect::where('search_id', $search->id)
            ->whereIn('audit_status', ['complete', 'skipped', 'failed'])
            ->count();

        if ($finishedCount >= $totalFound) {
            $search->update(['status' => 'complete']);
        }
    }
}
