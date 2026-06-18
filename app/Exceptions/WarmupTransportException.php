<?php

namespace App\Exceptions;

use App\Support\WarmupCredentialScrubber;
use Exception;
use Illuminate\Support\Str;

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

    public function isRecipientRejected(): bool
    {
        $message = Str::lower($this->getMessage());

        $patterns = [
            '550',
            '551',
            '552',
            '553',
            '554',
            'recipient rejected',
            'recipient address rejected',
            'user unknown',
            'mailbox unavailable',
            'does not exist',
            'no such user',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
