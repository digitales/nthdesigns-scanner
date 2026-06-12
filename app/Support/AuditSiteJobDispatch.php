<?php

namespace App\Support;

use App\Jobs\AuditSiteJob;
use App\Models\Prospect;
use Illuminate\Foundation\Bus\PendingDispatch;

final class AuditSiteJobDispatch
{
    /**
     * Queue a site audit with optional extra delay (e.g. repair batches).
     * Applies per-search stagger so large discoveries do not hit Fly in one wave.
     */
    public static function dispatch(Prospect $prospect, int $extraDelaySeconds = 0): PendingDispatch
    {
        $pending = AuditSiteJob::dispatch($prospect->fresh());
        $delay = QueueDispatchDelay::combine(
            self::staggerDelaySeconds($prospect),
            $extraDelaySeconds,
        );

        if ($delay > 0) {
            $pending->delay(now()->addSeconds($delay));
        }

        return $pending;
    }

    /**
     * Delay before this prospect's audit should start, based on its order in the search.
     * Capped at {@see QueueDispatchDelay::MAX_SECONDS} for SQS managed queues.
     */
    public static function staggerDelaySeconds(Prospect $prospect): int
    {
        $stagger = (int) config('scanner.audit_dispatch_stagger_seconds');

        if ($stagger <= 0) {
            return 0;
        }

        $ordinal = Prospect::query()
            ->where('search_id', $prospect->search_id)
            ->where('id', '<=', $prospect->id)
            ->count();

        return QueueDispatchDelay::forIndex(max(0, $ordinal - 1), $stagger);
    }
}
