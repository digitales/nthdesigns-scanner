<?php

namespace App\Services;

use App\Enums\ProspectValidatorStatus;
use App\Models\Prospect;

class ProspectValidatorService
{
    public function __construct(
        private ProspectValidationRulesService $rules,
        private ProspectValidationSignalMatcher $matcher,
    ) {}

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

    public function shouldSkipQualification(Prospect $prospect): bool
    {
        [$status, , $flags] = $this->assess($prospect);

        if ($status !== ProspectValidatorStatus::LowChance) {
            return false;
        }

        return $this->isDefinitiveLowChance($flags, $prospect);
    }

    /**
     * @param  array<string>  $flags
     */
    private function isDefinitiveLowChance(array $flags, Prospect $prospect): bool
    {
        if ($prospect->qualification_status === 'skip') {
            return true;
        }

        if (in_array('already_digitally_strong', $flags, true)) {
            return true;
        }

        if (in_array('corporate_or_franchise_confirmed', $flags, true)) {
            return true;
        }

        foreach ($flags as $flag) {
            if (str_starts_with($flag, 'franchise_signal:')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: ProspectValidatorStatus, 1: string, 2: array<string>}
     */
    private function assess(Prospect $prospect): array
    {
        if (filled($prospect->validator_override_status)) {
            $status = $prospect->validator_override_status instanceof ProspectValidatorStatus
                ? $prospect->validator_override_status
                : ProspectValidatorStatus::from($prospect->validator_override_status);
            $note = trim((string) $prospect->validator_override_note);
            $summary = $note !== ''
                ? "Operator override — {$note}."
                : 'Operator override — manual validation decision.';

            return [$status, $summary, ['operator_override']];
        }

        $qualStatus = $prospect->qualification_status;
        $score = $prospect->combined_score;
        $flags = [];

        if ($qualStatus === 'skip') {
            return [
                ProspectValidatorStatus::LowChance,
                'Corporate group or franchise confirmed — decision-making is not at practice level.',
                array_merge(['corporate_or_franchise_confirmed'], $prospect->qualification_flags ?? []),
            ];
        }

        $franchiseMatch = $this->matcher->match(
            $prospect,
            $this->rules->activeFranchiseSignals(),
            $this->rules->matchFields(),
        );

        if ($franchiseMatch !== null) {
            $flags[] = $this->franchiseFlag($franchiseMatch);

            return [
                ProspectValidatorStatus::LowChance,
                'Franchise or corporate group signals found — decision process likely extended.',
                array_merge($flags, $prospect->qualification_flags ?? []),
            ];
        }

        $reviewCount = $prospect->review_count ?? 0;

        if ($reviewCount > $this->rules->highReviewCount()) {
            $flags[] = 'high_review_count';
        }

        $hasDirectContact = filled($prospect->email) || filled($prospect->phone);

        if (! $hasDirectContact) {
            $flags[] = 'no_direct_contact';
        }

        $hasSignificantWeakness = $score !== null && $score > $this->rules->weaknessThresholdHigh();
        $isAlreadyStrong = $score !== null && $score < $this->rules->weaknessThresholdStrong();

        if ($hasSignificantWeakness) {
            $flags[] = 'significant_digital_gaps';
        }

        if ($qualStatus === 'qualified' && $hasSignificantWeakness) {
            return [
                ProspectValidatorStatus::HighChance,
                'Independent practice with significant GBP and accessibility gaps — strong outreach candidate.',
                $flags,
            ];
        }

        if ($qualStatus === 'qualified' && ! $isAlreadyStrong) {
            return [
                ProspectValidatorStatus::HighChance,
                'Independent practice verified — suitable for outreach.',
                $flags,
            ];
        }

        if ($isAlreadyStrong) {
            return [
                ProspectValidatorStatus::LowChance,
                'Business already has a strong digital presence — limited improvement opportunity.',
                array_merge($flags, ['already_digitally_strong']),
            ];
        }

        return [
            ProspectValidatorStatus::LowChance,
            'Mixed signals or insufficient qualification data — not recommended for cold outreach.',
            array_merge($flags, ['insufficient_qualification_data']),
        ];
    }

    /**
     * @param  array{pattern: string, field: string, source: string, signal_id: int|null}  $match
     */
    private function franchiseFlag(array $match): string
    {
        return 'franchise_signal:'.$match['pattern'].':'.$match['field'];
    }
}
