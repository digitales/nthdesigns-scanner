<?php

namespace App\Support;

use Illuminate\Foundation\Bus\PendingDispatch;

final class ScrapingQueue
{
    public const NAME = 'scraping';

    public static function connection(): string
    {
        return (string) config('scanner.scraping_queue_connection', config('queue.default'));
    }

    public static function apply(object $job): object
    {
        return $job->onConnection(self::connection())->onQueue(self::NAME);
    }

    public static function dispatch(object $job): PendingDispatch
    {
        return dispatch(self::apply($job));
    }

    public static function chain(PendingDispatch $pending): PendingDispatch
    {
        return $pending->onConnection(self::connection())->onQueue(self::NAME);
    }
}
