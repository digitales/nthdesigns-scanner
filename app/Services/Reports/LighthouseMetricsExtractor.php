<?php

namespace App\Services\Reports;

final class LighthouseMetricsExtractor
{
    /**
     * @param  array<string, mixed>  $lighthousePayload
     * @param  array<string, mixed>  $a11yPayload
     * @return array{performance: int|null, accessibility: int|null, seo: int|null, best_practices: int|null}
     */
    public function extract(array $lighthousePayload, array $a11yPayload): array
    {
        $lh = $lighthousePayload['lighthouse'] ?? $lighthousePayload ?? $a11yPayload['lighthouse'] ?? [];

        return [
            'performance' => isset($lh['performance']) ? (int) $lh['performance'] : null,
            'accessibility' => isset($lh['accessibility']) ? (int) $lh['accessibility'] : null,
            'seo' => isset($lh['seo']) ? (int) $lh['seo'] : null,
            'best_practices' => isset($lh['best_practices']) ? (int) $lh['best_practices'] : null,
        ];
    }

    /**
     * @param  array{performance: int|null, accessibility: int|null, seo: int|null, best_practices: int|null}  $lighthouse
     */
    public function hasMetrics(array $lighthouse): bool
    {
        return $lighthouse['performance'] !== null
            || $lighthouse['accessibility'] !== null
            || $lighthouse['seo'] !== null
            || $lighthouse['best_practices'] !== null;
    }
}
