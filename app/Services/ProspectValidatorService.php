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

        $hasDigitalWeakness = $score !== null && $score < 60;
        $hasStrongPresence = $score !== null && $score > 80;

        if ($hasDigitalWeakness) {
            $flags[] = 'significant_digital_gaps';
        }

        // Independently verified with clear weaknesses — best possible outreach candidate.
        if ($qualStatus === 'qualified' && $hasDigitalWeakness) {
            return [
                ProspectValidatorStatus::HighChance,
                'Independent practice with clear digital gaps — strong outreach candidate.',
                $flags,
            ];
        }

        // Independently verified, viable target even without a severe weakness.
        if ($qualStatus === 'qualified' && ! $hasStrongPresence) {
            return [
                ProspectValidatorStatus::HighChance,
                'Independent practice verified — suitable for outreach.',
                $flags,
            ];
        }

        // Already has a strong digital presence — limited opportunity to add value.
        if ($hasStrongPresence) {
            return [
                ProspectValidatorStatus::LowChance,
                'Business already has a strong digital presence — limited improvement opportunity.',
                array_merge($flags, ['strong_digital_presence']),
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
