<?php

namespace App\Support;

final class ReachabilityResult
{
    public function __construct(
        public readonly bool $reachable,
        public readonly ?string $failureMessage = null,
        public readonly bool $permanent = false,
    ) {}

    public static function ok(): self
    {
        return new self(reachable: true);
    }

    public static function failed(string $message, bool $permanent): self
    {
        return new self(reachable: false, failureMessage: $message, permanent: $permanent);
    }

    public function isReachable(): bool
    {
        return $this->reachable;
    }
}
