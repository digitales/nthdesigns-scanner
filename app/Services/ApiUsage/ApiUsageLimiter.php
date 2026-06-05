<?php

namespace App\Services\ApiUsage;

use App\Exceptions\ApiQuotaExceededException;

class ApiUsageLimiter
{
    public function __construct(
        private ApiUsageRecorder $recorder,
        private ApiQuotaSettingsService $quotaSettings,
    ) {}

    /**
     * @return array{status: string, daily: array{count: int, limit: int, pct: float}, monthly: array{count: int, limit: int, pct: float}}
     */
    public function snapshot(string $provider, string $operation): array
    {
        $daily = $this->periodSnapshot($provider, $operation, 'daily');
        $monthly = $this->periodSnapshot($provider, $operation, 'monthly');

        return [
            'status' => $this->resolveStatus($daily['pct'], $monthly['pct']),
            'daily' => $daily,
            'monthly' => $monthly,
        ];
    }

    public function assertWithinQuota(string $provider, string $operation): void
    {
        if (! config('scanner.api_quota.enforcement', true)) {
            return;
        }

        foreach (['daily', 'monthly'] as $periodType) {
            $count = $this->recorder->countFor($provider, $operation, $periodType);
            $limit = $this->quotaSettings->effectiveLimit($provider, $operation, $periodType);

            if ($limit > 0 && $count >= $limit) {
                throw new ApiQuotaExceededException(
                    $provider,
                    $operation,
                    $periodType,
                    $count,
                    $limit,
                );
            }
        }
    }

    /**
     * @return array{count: int, limit: int, pct: float}
     */
    private function periodSnapshot(string $provider, string $operation, string $periodType): array
    {
        $count = $this->recorder->countFor($provider, $operation, $periodType);
        $limit = $this->quotaSettings->effectiveLimit($provider, $operation, $periodType);
        $pct = $limit > 0 ? min(100, round(($count / $limit) * 100, 1)) : 0.0;

        return compact('count', 'limit', 'pct');
    }

    private function resolveStatus(float $dailyPct, float $monthlyPct): string
    {
        $warning = (int) config('scanner.api_quota.warning_percent', 80);

        if ($dailyPct >= 100 || $monthlyPct >= 100) {
            return 'blocked';
        }

        if ($dailyPct >= $warning || $monthlyPct >= $warning) {
            return 'warning';
        }

        return 'ok';
    }
}
