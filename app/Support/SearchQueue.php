<?php

namespace App\Support;

final class SearchQueue
{
    public const NAME = 'searches';

    public static function connection(): string
    {
        return (string) config('scanner.search_queue_connection', config('queue.default'));
    }
}
