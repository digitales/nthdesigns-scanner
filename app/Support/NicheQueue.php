<?php

namespace App\Support;

use Illuminate\Foundation\Bus\PendingDispatch;

final class NicheQueue
{
    public const NAME = 'niches';

    public static function connection(): string
    {
        return (string) config('scanner.niche_queue_connection', config('queue.default'));
    }

    public static function dispatch(object $job): PendingDispatch
    {
        return dispatch($job);
    }

    public static function chain(PendingDispatch $pending): PendingDispatch
    {
        return $pending->onConnection(self::connection())->onQueue(self::NAME);
    }
}
