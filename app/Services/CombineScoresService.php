<?php

namespace App\Services;

use App\Enums\ScanType;
use App\Models\Prospect;

class CombineScoresService
{
    /**
     * Scan type for scoring and display. GBP-only searches upgrade to combined
     * once this prospect has site-audit payload (batch or ad-hoc "Run site audit").
     */
    public function effectiveScanType(Prospect $prospect, string|ScanType $searchScanType): string
    {
        $searchScanType = $this->normalizeScanType($searchScanType);

        if ($searchScanType === 'gbp_only' && $this->prospectHasSiteAudit($prospect)) {
            return 'combined';
        }

        return $searchScanType;
    }

    /**
     * @return array{combined_score: int, dominant_angle: string}
     */
    public function combineForProspect(Prospect $prospect, string|ScanType|null $searchScanType = null): array
    {
        $searchScanType ??= $prospect->search?->scan_type ?? ScanType::GbpOnly;

        return $this->combine($prospect, $this->effectiveScanType($prospect, $searchScanType));
    }

    /**
     * Merge GBP and accessibility scores based on scan type.
     *
     * @return array{combined_score: int, dominant_angle: string}
     */
    public function combine(Prospect $prospect, string $scanType): array
    {
        $gbp = (int) $prospect->gbp_score;
        $a11y = (int) $prospect->a11y_score;

        return match ($scanType) {
            'gbp_only' => [
                'combined_score' => $gbp,
                'dominant_angle' => 'gbp',
            ],
            'accessibility_only' => [
                'combined_score' => $a11y,
                'dominant_angle' => 'accessibility',
            ],
            'combined' => $this->combineBoth($gbp, $a11y, (int) $prospect->performance_score),
            default => [
                'combined_score' => $gbp,
                'dominant_angle' => 'gbp',
            ],
        };
    }

    public function performanceWeakness(int $performanceScore): int
    {
        return $performanceScore > 0 ? 100 - $performanceScore : 0;
    }

    /**
     * @return array{combined_score: int, dominant_angle: string}
     */
    private function combineBoth(int $gbp, int $a11y, int $performanceScore): array
    {
        $perfWeakness = $this->performanceWeakness($performanceScore);

        $combined = (int) round(
            ($gbp * 0.35) + ($a11y * 0.50) + ($perfWeakness * 0.15)
        );

        $dominant = 'both';
        if ($a11y > 70) {
            $dominant = 'accessibility';
        } elseif ($gbp > 70) {
            $dominant = 'gbp';
        }

        return [
            'combined_score' => $combined,
            'dominant_angle' => $dominant,
        ];
    }

    private function prospectHasSiteAudit(Prospect $prospect): bool
    {
        $payload = $prospect->raw_a11y_payload;

        return is_array($payload) && $payload !== [];
    }

    private function normalizeScanType(string|ScanType $scanType): string
    {
        return $scanType instanceof ScanType ? $scanType->value : $scanType;
    }
}
