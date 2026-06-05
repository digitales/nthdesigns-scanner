<?php

namespace App\Services;

use App\Models\IgnoredNiche;
use App\Models\NicheInclusionOverride;
use App\Models\NicheScan;
use App\Queries\LatestNicheScanQuery;

final class NicheExclusionService
{
    /**
     * @return list<string>
     */
    public function ignoredLabels(): array
    {
        return IgnoredNiche::query()
            ->orderBy('niche')
            ->pluck('niche')
            ->all();
    }

    public function isIgnored(string $niche): bool
    {
        return IgnoredNiche::query()->where('niche', $niche)->exists();
    }

    public function ignoreManually(string $niche): void
    {
        IgnoredNiche::query()->updateOrCreate(
            ['niche' => $niche],
            ['reason' => IgnoredNiche::REASON_MANUAL],
        );

        NicheInclusionOverride::query()->where('niche', $niche)->delete();
    }

    public function includeInScans(string $niche): void
    {
        $ignored = IgnoredNiche::query()->where('niche', $niche)->first();

        if ($ignored === null) {
            return;
        }

        if ($ignored->reason === IgnoredNiche::REASON_LOW_RESULTS) {
            NicheInclusionOverride::query()->firstOrCreate(['niche' => $niche]);
        }

        $ignored->delete();
    }

    public function refreshForNiche(string $niche): void
    {
        if (NicheInclusionOverride::query()->where('niche', $niche)->exists()) {
            IgnoredNiche::query()
                ->where('niche', $niche)
                ->where('reason', IgnoredNiche::REASON_LOW_RESULTS)
                ->delete();

            return;
        }

        if (IgnoredNiche::query()
            ->where('niche', $niche)
            ->where('reason', IgnoredNiche::REASON_MANUAL)
            ->exists()) {
            return;
        }

        $maxResults = $this->maxLatestResultCount($niche);
        $minResults = max(1, (int) config('niches.min_result_count', 3));

        if ($maxResults === null) {
            return;
        }

        if ($maxResults < $minResults) {
            IgnoredNiche::query()->updateOrCreate(
                ['niche' => $niche],
                ['reason' => IgnoredNiche::REASON_LOW_RESULTS],
            );

            return;
        }

        IgnoredNiche::query()
            ->where('niche', $niche)
            ->where('reason', IgnoredNiche::REASON_LOW_RESULTS)
            ->delete();
    }

    public function syncAllLowResultExclusions(): int
    {
        $changed = 0;

        foreach (collect(config('niches.niches', []))->pluck('label') as $label) {
            $before = IgnoredNiche::query()->where('niche', $label)->value('reason');
            $this->refreshForNiche($label);
            $after = IgnoredNiche::query()->where('niche', $label)->value('reason');

            if ($before !== $after) {
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * @return list<array{
     *     label: string,
     *     query: string,
     *     ignored: bool,
     *     ignore_reason: string|null,
     *     max_result_count: int|null,
     *     is_low_result: bool
     * }>
     */
    public function catalog(): array
    {
        $ignored = IgnoredNiche::query()->pluck('reason', 'niche');
        $minResults = max(1, (int) config('niches.min_result_count', 3));

        return collect(config('niches.niches', []))
            ->map(function (array $entry) use ($ignored, $minResults) {
                $label = $entry['label'];
                $maxResults = $this->maxLatestResultCount($label);

                return [
                    'label' => $label,
                    'query' => $entry['query'],
                    'ignored' => $ignored->has($label),
                    'ignore_reason' => $ignored->get($label),
                    'max_result_count' => $maxResults,
                    'is_low_result' => $maxResults !== null && $maxResults < $minResults,
                ];
            })
            ->values()
            ->all();
    }

    private function maxLatestResultCount(string $niche): ?int
    {
        $latestIds = LatestNicheScanQuery::ranked(
            fn ($query) => $query->where('niche', $niche)->where('status', 'complete'),
        )->pluck('result_count');

        if ($latestIds->isEmpty()) {
            return null;
        }

        return (int) $latestIds->max();
    }
}
