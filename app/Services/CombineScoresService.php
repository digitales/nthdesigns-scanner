<?php

namespace App\Services;

use App\Models\Prospect;

class CombineScoresService
{
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
            'combined' => $this->combineBoth($gbp, $a11y),
            default => [
                'combined_score' => $gbp,
                'dominant_angle' => 'gbp',
            ],
        };
    }

    /**
     * @return array{combined_score: int, dominant_angle: string}
     */
    private function combineBoth(int $gbp, int $a11y): array
    {
        $combined = (int) round(($gbp + $a11y) / 2);

        $dominant = 'both';

        if ($gbp >= $a11y + 15) {
            $dominant = 'gbp';
        } elseif ($a11y >= $gbp + 15) {
            $dominant = 'accessibility';
        }

        return [
            'combined_score' => $combined,
            'dominant_angle' => $dominant,
        ];
    }
}
