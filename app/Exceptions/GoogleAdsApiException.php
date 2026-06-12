<?php

namespace App\Exceptions;

use RuntimeException;

class GoogleAdsApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>|null  $response
     */
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?array $response = null,
    ) {
        parent::__construct($message);
    }
}
