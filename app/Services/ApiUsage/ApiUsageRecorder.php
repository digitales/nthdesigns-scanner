<?php

namespace App\Services\ApiUsage;

use App\Models\ApiUsageCounter;
use Carbon\CarbonInterface;

class ApiUsageRecorder
{
    public function __construct(
        private string $timezone = 'Europe/London',
    ) {}

    public function increment(string $provider, string $operation, ?CarbonInterface $at = null): void
    {
        $at = ($at ?? now())->timezone($this->timezone);

        foreach ($this->periodKeys($at) as $periodType => $periodKey) {
            $counter = ApiUsageCounter::query()->firstOrCreate(
                [
                    'provider' => $provider,
                    'operation' => $operation,
                    'period_type' => $periodType,
                    'period_key' => $periodKey,
                ],
                ['count' => 0],
            );

            $counter->increment('count');
        }
    }

    public function countFor(string $provider, string $operation, string $periodType, ?CarbonInterface $at = null): int
    {
        $at = ($at ?? now())->timezone($this->timezone);
        $periodKey = $this->periodKeys($at)[$periodType];

        return (int) ApiUsageCounter::query()
            ->where('provider', $provider)
            ->where('operation', $operation)
            ->where('period_type', $periodType)
            ->where('period_key', $periodKey)
            ->value('count');
    }

    /**
     * @return array{daily: string, monthly: string}
     */
    private function periodKeys(CarbonInterface $at): array
    {
        return [
            'daily' => $at->toDateString(),
            'monthly' => $at->format('Y-m'),
        ];
    }
}
