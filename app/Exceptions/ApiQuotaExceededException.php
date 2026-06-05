<?php

namespace App\Exceptions;

use Exception;

class ApiQuotaExceededException extends Exception
{
    public function __construct(
        public readonly string $provider,
        public readonly string $operation,
        public readonly string $periodType,
        public readonly int $count,
        public readonly int $limit,
    ) {
        parent::__construct(sprintf(
            '%s %s %s quota exceeded (%d/%d)',
            str_replace('_', ' ', $provider),
            str_replace('_', ' ', $operation),
            $periodType,
            $count,
            $limit,
        ));
    }
}
