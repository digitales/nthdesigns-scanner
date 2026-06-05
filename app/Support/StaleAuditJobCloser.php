<?php

namespace App\Support;

use App\Models\AuditJob;

final class StaleAuditJobCloser
{
    public const MESSAGE = 'Closed by scanner:repair-audits (stale)';

    public static function closeRunning(int $prospectId, string $jobType): int
    {
        return AuditJob::query()
            ->where('prospect_id', $prospectId)
            ->where('job_type', $jobType)
            ->where('status', 'running')
            ->update([
                'status' => 'failed',
                'error_message' => self::MESSAGE,
                'completed_at' => now(),
            ]);
    }
}
