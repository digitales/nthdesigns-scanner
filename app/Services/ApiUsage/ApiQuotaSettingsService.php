<?php

namespace App\Services\ApiUsage;

use App\Models\ApiQuotaSetting;
use App\Support\ApiUsage\ApiOperation;

class ApiQuotaSettingsService
{
    public function settings(): ApiQuotaSetting
    {
        return ApiQuotaSetting::current();
    }

    public function envLimit(string $provider, string $operation, string $periodType): int
    {
        return max(0, (int) data_get(
            config('scanner.api_quota.limits'),
            "{$provider}.{$operation}.{$periodType}",
            0,
        ));
    }

    public function effectiveLimit(string $provider, string $operation, string $periodType): int
    {
        $ceiling = $this->envLimit($provider, $operation, $periodType);
        $column = ApiOperation::settingsColumn($provider, $operation, $periodType);
        $override = $this->settings()->getAttribute($column);

        if ($override === null) {
            return $ceiling;
        }

        return min($ceiling, max(0, (int) $override));
    }

    public function costPencePerCall(string $provider, string $operation): float
    {
        return (float) data_get(
            config('scanner.api_quota.cost_pence'),
            "{$provider}.{$operation}",
            0,
        );
    }

    /**
     * @return array<string, int>
     */
    public function envCeilingsFor(string $provider, string $operation): array
    {
        return [
            'daily' => $this->envLimit($provider, $operation, 'daily'),
            'monthly' => $this->envLimit($provider, $operation, 'monthly'),
        ];
    }

    /**
     * @param  array<string, int|null>  $attributes
     */
    public function update(array $attributes): ApiQuotaSetting
    {
        $settings = $this->settings();
        $settings->update($attributes);

        return $settings->fresh();
    }
}
