<?php

namespace App\Services;

use App\Models\ProspectValidationSignal;
use Illuminate\Support\Collection;

class ProspectValidationRulesService
{
    /** @var Collection<int, array{pattern: string, source: string, signal_id: int|null, label: string|null}>|null */
    private ?Collection $cachedSignals = null;

    /**
     * @return Collection<int, array{pattern: string, source: string, signal_id: int|null, label: string|null}>
     */
    public function activeFranchiseSignals(): Collection
    {
        if ($this->cachedSignals !== null) {
            return $this->cachedSignals;
        }

        $merged = collect();

        foreach (ProspectValidationSignal::query()->where('active', true)->orderBy('id')->get() as $signal) {
            $merged->put($signal->pattern, [
                'pattern' => $signal->pattern,
                'source' => 'operator',
                'signal_id' => $signal->id,
                'label' => $signal->label,
            ]);
        }

        foreach (config('prospect_validator.franchise_signals', []) as $pattern) {
            $normalised = strtolower(trim((string) $pattern));

            if ($normalised === '' || $merged->has($normalised)) {
                continue;
            }

            $merged->put($normalised, [
                'pattern' => $normalised,
                'source' => 'config',
                'signal_id' => null,
                'label' => null,
            ]);
        }

        $this->cachedSignals = $merged->values();

        return $this->cachedSignals;
    }

    /**
     * @return list<string>
     */
    public function builtInSignalPatterns(): array
    {
        return collect(config('prospect_validator.franchise_signals', []))
            ->map(fn ($pattern) => strtolower(trim((string) $pattern)))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function matchFields(): array
    {
        return config('prospect_validator.match_fields', []);
    }

    public function weaknessThresholdHigh(): int
    {
        return (int) config('prospect_validator.weakness_threshold_high', 60);
    }

    public function weaknessThresholdStrong(): int
    {
        return (int) config('prospect_validator.weakness_threshold_strong', 25);
    }

    public function highReviewCount(): int
    {
        return (int) config('prospect_validator.high_review_count', 500);
    }

    public function clearCache(): void
    {
        $this->cachedSignals = null;
    }
}
