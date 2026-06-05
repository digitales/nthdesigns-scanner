<?php

namespace App\Support;

class ViolationCounter
{
    /**
     * @param  list<array<string, mixed>>  $violations
     * @return array{critical: int, serious: int, moderate: int, minor: int}
     */
    public static function countByImpact(array $violations): array
    {
        $counts = ['critical' => 0, 'serious' => 0, 'moderate' => 0, 'minor' => 0];

        foreach ($violations as $violation) {
            $impact = $violation['impact'] ?? 'minor';
            if (isset($counts[$impact])) {
                $counts[$impact]++;
            }
        }

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{critical: int, serious: int, moderate: int, minor: int, total: int}
     */
    public static function summarizePayload(array $payload): array
    {
        $counts = self::countByImpact($payload['violations'] ?? []);
        $counts['total'] = array_sum($counts);

        return $counts;
    }
}
