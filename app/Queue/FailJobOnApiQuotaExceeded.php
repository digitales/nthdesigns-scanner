<?php

namespace App\Queue;

use App\Exceptions\ApiQuotaExceededException;
use Closure;
use Illuminate\Queue\InteractsWithQueue;

class FailJobOnApiQuotaExceeded
{
    public function handle(object $command, Closure $next): mixed
    {
        try {
            return $next($command);
        } catch (ApiQuotaExceededException $e) {
            if (in_array(InteractsWithQueue::class, class_uses_recursive($command), true)
                && method_exists($command, 'fail')) {
                $command->fail($e);

                return null;
            }

            throw $e;
        }
    }
}
