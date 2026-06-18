<?php

namespace App\Services;

use App\Enums\ProspectValidatorStatus;
use App\Models\Prospect;

class ProspectValidatorService
{
    /**
     * Known franchise/corporate brand signals — businesses where the decision-maker
     * is not at practice level, making the sales cycle prohibitively long.
     */
    private const FRANCHISE_SIGNALS = [
        'portman', 'mydentist', 'bupa dental', 'dental care alliance',
        'hsone', 'dentalnode', 'multiple location', 'national coverage',
        'corporate booking', 'group entity', 'part of',
    ];

    /**
     * combined_score is a composite WEAKNESS score (higher = more GBP gaps +
     * accessibility violations + performance issues = more opportunity for the agency).
     *
     * Thresholds:
     *   > 60  — significant weaknesses, strong outreach case
     *   25–60  — moderate, still viable
     *   < 25  — business is already digitally strong, limited opportunity
     */
    private const WEAKNESS_THRESHOLD_HIGH = 60;
    private const WEAKNESS_THRESHOLD_STRONG = 25;

    public function validate(Prospect $prospect): void
    {
        [$status, $summary, $flags] = $this->assess($prospect);

        $prospect->update([
            'validator_status' => $status->value,
            'validator_summary' => $summary,
            'validator_flags' => $flags,
            'validator_ran_at' => now(),
        ]);
    }

    /**
     * @return array{0: ProspectValidatorStatus, 1: string, 2: array<string>}
     */
    private function assess(Prospect $prospect): array
    {
        $qualStatus = $prospect->qualification_status;
        $qualFlags = array_map('strtolower', $prospect->qualification_flags ?? []);
        $score = $prospect->combined_score;
        $flags = [];

        // Definitive franchise/corporate — decision-making is not at practice level.
        if ($qualStatus === 'skip') {
            return [
                ProspectValidatorStatus::LowChance,
                'Corporate group or franchise confirmed — decision-making is not at practice level.',
                array_merge(['corporate_or_franchise_confirmed'], $prospect->qualification_flags ?? []),
            ];
        }

        // Check for franchise brand signals buried in qualification flags.
        $hasFranchiseSignal = false;

        foreach (self::FRANCHISE_SIGNALS as $signal) {
            foreach ($qualFlags as $flag) {
                if (str_contains($flag, $signal)) {
                    $hasFranchiseSignal = true;
                    $flags[] = 'franchise_signal_in_flags';
                    break 2;
                }
            }
        }

        if ($hasFranchiseSignal) {
            return [
                ProspectValidatorStatus::LowChance,
                'Franchise or corporate group signals found — decision process likely extended.',
                array_merge($flags, $prospect->qualification_flags ?? []),
            ];
        }

        // High review count can indicate a larger, slower-moving organisation.
        $reviewCount = $prospect->review_count ?? 0;
        if ($reviewCount > 500) {
            $flags[] = 'high_review_count';
        }

        $hasDirectContact = filled($prospect->email) || filled($prospect->phone);
        if (! $hasDirectContact) {
            $flags[] = 'no_direct_contact';
        }

        // combined_score is a weakness score: high = lots of GBP gaps + accessibility issues.
        $hasSignificantWeakness = $score !== null && $score > self::WEAKNESS_THRESHOLD_HIGH;
        $isAlreadyStrong = $score !== null && $score < self::WEAKNESS_THRESHOLD_STRONG;

        if ($hasSignificantWeakness) {
            $flags[] = 'significant_digital_gaps';
        }

        // Independently verified + clear digital weaknesses — best outreach candidate.
        if ($qualStatus === 'qualified' && $hasSignificantWeakness) {
            return [
                ProspectValidatorStatus::HighChance,
                'Independent practice with significant GBP and accessibility gaps — strong outreach candidate.',
                $flags,
            ];
        }

        // Independently verified, some weakness but still a viable target.
        if ($qualStatus === 'qualified' && ! $isAlreadyStrong) {
            return [
                ProspectValidatorStatus::HighChance,
                'Independent practice verified — suitable for outreach.',
                $flags,
            ];
        }

        // Already strong digitally — limited opportunity to add value.
        if ($isAlreadyStrong) {
            return [
                ProspectValidatorStatus::LowChance,
                'Business already has a strong digital presence — limited improvement opportunity.',
                array_merge($flags, ['already_digitally_strong']),
            ];
        }

        // Caution status or no qualification data — not confident enough to recommend.
        return [
            ProspectValidatorStatus::LowChance,
            'Mixed signals or insufficient qualification data — not recommended for cold outreach.',
            array_merge($flags, ['insufficient_qualification_data']),
        ];
    }
}
