<?php

namespace App\Support;

use App\Jobs\AuditSiteJob;
use App\Jobs\CaptureScreenshotJob;
use Illuminate\Support\Facades\DB;

final class AuditingQueuePresence
{
    public static function skipsQueueCheck(): bool
    {
        return AuditingQueue::connection() === 'cloud';
    }

    public static function hasPendingAuditSiteJob(int $prospectId): bool
    {
        if (self::skipsQueueCheck()) {
            return false;
        }

        return self::hasPendingJob(AuditSiteJob::class, $prospectId);
    }

    public static function hasPendingScreenshotJob(int $reportId): bool
    {
        if (self::skipsQueueCheck()) {
            return false;
        }

        return self::hasPendingJob(CaptureScreenshotJob::class, $reportId);
    }

    private static function hasPendingJob(string $jobClass, int $modelId): bool
    {
        $shortName = class_basename($jobClass);

        $queueConfig = config('queue.connections.'.AuditingQueue::connection(), []);
        $dbConnection = $queueConfig['connection'] ?? null;
        $table = $queueConfig['table'] ?? 'jobs';

        $query = $dbConnection !== null
            ? DB::connection($dbConnection)->table($table)
            : DB::table($table);

        return $query
            ->where('queue', AuditingQueue::NAME)
            ->whereNull('reserved_at')
            ->where('payload', 'like', '%'.$shortName.'%')
            ->where('payload', 'like', '%\"id\";i:'.$modelId.';%')
            ->exists();
    }
}
