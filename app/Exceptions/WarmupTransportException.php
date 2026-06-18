<?php

namespace App\Exceptions;

use App\Support\WarmupCredentialScrubber;
use Exception;

class WarmupTransportException extends Exception
{
    public static function fromThrowable(\Throwable $e): self
    {
        return new self(
            WarmupCredentialScrubber::scrub($e->getMessage()),
            (int) $e->getCode(),
            $e,
        );
    }
}
