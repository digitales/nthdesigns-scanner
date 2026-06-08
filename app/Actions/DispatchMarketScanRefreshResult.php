<?php

namespace App\Actions;

final readonly class DispatchMarketScanRefreshResult
{
    private function __construct(
        public string $outcome,
        public int $rateLimitSeconds = 0,
    ) {}

    public static function queued(): self
    {
        return new self('queued');
    }

    public static function alreadyPending(): self
    {
        return new self('already_pending');
    }

    public static function rateLimited(int $seconds): self
    {
        return new self('rate_limited', $seconds);
    }

    public function isQueued(): bool
    {
        return $this->outcome === 'queued';
    }

    public function isAlreadyPending(): bool
    {
        return $this->outcome === 'already_pending';
    }

    public function isRateLimited(): bool
    {
        return $this->outcome === 'rate_limited';
    }
}
