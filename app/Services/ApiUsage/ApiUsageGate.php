<?php

namespace App\Services\ApiUsage;

class ApiUsageGate
{
    public function __construct(
        private ApiUsageLimiter $limiter,
        private ApiUsageRecorder $recorder,
    ) {}

    public function assertWithinQuota(string $provider, string $operation): void
    {
        $this->limiter->assertWithinQuota($provider, $operation);
    }

    public function recordCompletedRequest(string $provider, string $operation): void
    {
        $this->recorder->increment($provider, $operation);
    }
}
