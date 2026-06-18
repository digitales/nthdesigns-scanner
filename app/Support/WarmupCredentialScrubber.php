<?php

namespace App\Support;

class WarmupCredentialScrubber
{
    public static function scrub(string $message): string
    {
        return preg_replace(
            '/[a-z][a-z0-9+.-]*:\/\/[^@\s]+@[^\s]+/i',
            '[credentials redacted]',
            $message,
        ) ?? $message;
    }
}
