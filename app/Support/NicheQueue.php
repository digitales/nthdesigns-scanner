<?php

namespace App\Support;

final class NicheQueue
{
    public const NAME = 'niches';

    public static function connection(): string
    {
        return (string) config('scanner.niche_queue_connection', config('queue.default'));
    }
}
