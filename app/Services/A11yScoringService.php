<?php

namespace App\Services;

class A11yScoringService
{
    /**
     * Score a raw audit payload for accessibility weakness.
     * Returns ['score' => int (0-100), 'flags' => string[]]
     * Higher score = weaker site = better prospect.
     */
    public function score(array $payload): array
    {
        $score = 0;
        $flags = [];

        if (!empty($payload['error'])) {
            return [
                'score' => 50,
                'flags' => ['Site audit failed to load'],
            ];
        }

        $violations = $payload['violations'] ?? [];
        $counts = ['critical' => 0, 'serious' => 0, 'moderate' => 0, 'minor' => 0];

        foreach ($violations as $violation) {
            $impact = $violation['impact'] ?? 'minor';
            if (isset($counts[$impact])) {
                $counts[$impact]++;
            }
        }

        if ($counts['critical'] > 0) {
            $points = min($counts['critical'] * 15, 30);
            $score += $points;
            $flags[] = "{$counts['critical']} critical accessibility issue(s)";
        }

        if ($counts['serious'] > 0) {
            $points = min($counts['serious'] * 8, 24);
            $score += $points;
            $flags[] = "{$counts['serious']} serious accessibility issue(s)";
        }

        if ($counts['moderate'] > 0) {
            $points = min($counts['moderate'] * 4, 16);
            $score += $points;
            $flags[] = "{$counts['moderate']} moderate accessibility issue(s)";
        }

        if ($counts['minor'] > 0 && $score < 60) {
            $score += min($counts['minor'] * 2, 10);
        }

        $performance = $payload['lighthouse']['performance'] ?? null;

        $lhA11y = $payload['lighthouse']['accessibility'] ?? null;

        if ($lhA11y !== null && $lhA11y < 70) {
            $score += 10;
            $flags[] = 'Lighthouse accessibility below 70';
        }

        if (count($violations) === 0 && $performance === null) {
            $flags[] = 'No violations detected';
        }

        return [
            'score' => min($score, 100),
            'flags' => array_values(array_unique($flags)),
        ];
    }

    /**
     * Extract performance score from audit payload for storage.
     */
    public function extractPerformanceScore(array $payload): int
    {
        return (int) ($payload['lighthouse']['performance'] ?? 0);
    }
}
