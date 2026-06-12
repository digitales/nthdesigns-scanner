<?php

namespace App\Support;

final class QueueDispatchDelay
{
    /** AWS SQS maximum DelaySeconds per message. */
    public const MAX_SECONDS = 900;

    public static function forIndex(int $index, int $stepSeconds): int
    {
        if ($stepSeconds <= 0) {
            return 0;
        }

        return min($index * $stepSeconds, self::MAX_SECONDS);
    }

    /**
     * Maximum jobs that can be staggered in one dispatch batch without exceeding SQS limits.
     */
    public static function maxJobsPerBatch(int $stepSeconds): ?int
    {
        if ($stepSeconds <= 0) {
            return null;
        }

        return intdiv(self::MAX_SECONDS, $stepSeconds) + 1;
    }

    /**
     * Sum delay parts for a single dispatch, capped at the SQS maximum.
     */
    public static function combine(int ...$parts): int
    {
        $total = 0;

        foreach ($parts as $part) {
            $total += max(0, $part);
        }

        return min($total, self::MAX_SECONDS);
    }
}
