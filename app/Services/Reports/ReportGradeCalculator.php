<?php

namespace App\Services\Reports;

final class ReportGradeCalculator
{
    /** @see resources/css/tokens.css — grade thresholds from combined score */
    public function combinedToGrade(int $combinedScore): string
    {
        return match (true) {
            $combinedScore >= 85 => 'D',
            $combinedScore >= 70 => 'C',
            $combinedScore >= 50 => 'C+',
            $combinedScore >= 30 => 'B',
            default => 'B+',
        };
    }

    public function healthToGrade(int $healthScore): string
    {
        return $this->combinedToGrade(max(0, min(100, 100 - $healthScore)));
    }

    public function gradeLabel(string $grade): string
    {
        return match ($grade) {
            'A' => 'Strong online presence',
            'B' => 'Good with room to improve',
            'C' => 'Several gaps to address',
            'D' => 'Needs attention',
            default => 'Significant issues found',
        };
    }
}
