<?php

namespace App\Services\Reports;

use App\Support\AxeViolationCopy;
use App\Support\ViolationCounter;

class ViolationMapper
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{critical: int, serious: int, moderate: int, minor: int, total: int}
     */
    public function summarize(array $payload): array
    {
        return ViolationCounter::summarizePayload($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public function all(array $payload): array
    {
        return $this->map($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public function top(array $payload, int $limit = 5): array
    {
        return array_slice($this->map($payload), 0, $limit);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function map(array $payload): array
    {
        $violations = $payload['violations'] ?? [];
        $impactOrder = ['critical' => 0, 'serious' => 1, 'moderate' => 2, 'minor' => 3];

        usort($violations, function (array $a, array $b) use ($impactOrder) {
            $ia = $impactOrder[$a['impact'] ?? 'minor'] ?? 4;
            $ib = $impactOrder[$b['impact'] ?? 'minor'] ?? 4;

            return $ia <=> $ib;
        });

        $screenshotMap = collect($payload['violation_screenshots'] ?? [])
            ->keyBy('violation_id');

        return array_map(function (array $violation) use ($screenshotMap) {
            $tags = $violation['tags'] ?? [];
            $wcag = collect($tags)->first(fn ($tag) => preg_match('/^wcag\d+/', (string) $tag));
            $id = $violation['id'] ?? 'issue';
            $copy = AxeViolationCopy::forRule($id);

            return [
                'id' => $id,
                'impact' => $violation['impact'] ?? 'moderate',
                'description' => $violation['description'] ?? $violation['help'] ?? 'Accessibility issue detected',
                'help' => $violation['help'] ?? null,
                'wcag' => $wcag ? strtoupper($wcag) : null,
                'nodes' => count($violation['nodes'] ?? []),
                'screenshot_url' => $screenshotMap->get($id)['url'] ?? null,
                'user_impact' => $copy['user_impact'],
                'fix_hint' => $copy['fix_hint'],
            ];
        }, $violations);
    }
}
