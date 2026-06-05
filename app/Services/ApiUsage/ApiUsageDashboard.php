<?php

namespace App\Services\ApiUsage;

use App\Support\ApiUsage\ApiOperation;

class ApiUsageDashboard
{
    public function __construct(
        private ApiUsageLimiter $limiter,
        private ApiQuotaSettingsService $quotaSettings,
    ) {}

    /**
     * @return array{
     *     warning_percent: int,
     *     operations: list<array{
     *         key: string,
     *         label: string,
     *         status: string,
     *         cost_pence_per_call: float,
     *         ceilings: array{daily: int, monthly: int},
     *         overrides: array{daily: int|null, monthly: int|null},
     *         daily: array{count: int, limit: int, pct: float, estimated_cost_pence: float, status: string},
     *         monthly: array{count: int, limit: int, pct: float, estimated_cost_pence: float, status: string},
     *     }>
     * }
     */
    public function snapshot(): array
    {
        $warningPercent = (int) config('scanner.api_quota.warning_percent', 80);
        $operations = [];

        foreach (ApiOperation::all() as $definition) {
            $provider = $definition['provider'];
            $operation = $definition['operation'];
            $usage = $this->limiter->snapshot($provider, $operation);
            $cost = $this->quotaSettings->costPencePerCall($provider, $operation);
            $settings = $this->quotaSettings->settings();

            $operations[] = [
                'key' => $definition['key'],
                'label' => $definition['label'],
                'status' => $usage['status'],
                'cost_pence_per_call' => $cost,
                'ceilings' => $this->quotaSettings->envCeilingsFor($provider, $operation),
                'overrides' => [
                    'daily' => $settings->getAttribute(ApiOperation::settingsColumn($provider, $operation, 'daily')),
                    'monthly' => $settings->getAttribute(ApiOperation::settingsColumn($provider, $operation, 'monthly')),
                ],
                'daily' => $this->periodPayload($usage['daily'], $cost, $warningPercent),
                'monthly' => $this->periodPayload($usage['monthly'], $cost, $warningPercent),
            ];
        }

        return [
            'warning_percent' => $warningPercent,
            'operations' => $operations,
        ];
    }

    /**
     * @param  array{count: int, limit: int, pct: float}  $period
     * @return array{count: int, limit: int, pct: float, estimated_cost_pence: float, status: string}
     */
    private function periodPayload(array $period, float $costPence, int $warningPercent): array
    {
        $status = 'ok';

        if ($period['pct'] >= 100) {
            $status = 'blocked';
        } elseif ($period['pct'] >= $warningPercent) {
            $status = 'warning';
        }

        return [
            ...$period,
            'estimated_cost_pence' => round($period['count'] * $costPence, 2),
            'status' => $status,
        ];
    }
}
