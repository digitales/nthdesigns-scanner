<?php

namespace App\Console\Commands;

use App\Jobs\ScanNicheJob;
use App\Models\NicheScan;
use App\Services\NicheExclusionService;
use App\Support\NicheQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ScanNichesCommand extends Command
{
    protected $signature = 'niches:scan
        {--cities=}
        {--niches=}
        {--sample=5}
        {--force : Dispatch even when today\'s scan is already complete}
        {--include-ignored : Scan niches on the ignore list}';

    protected $description = 'Dispatch niche×city GBP sample scans to the niches queue';

    public function handle(NicheExclusionService $exclusions): int
    {
        $defaultCities = collect(config('niches.cities', []))->filter()->implode(',');
        if ($defaultCities === '') {
            $defaultCities = 'Birmingham,Manchester,Leeds,Bristol,Edinburgh';
        }

        $citiesOption = (string) $this->option('cities');
        $cities = collect(explode(',', $citiesOption !== '' ? $citiesOption : $defaultCities))
            ->map(fn (string $c) => trim($c))
            ->filter()
            ->values();

        $nicheFilter = collect(explode(',', (string) $this->option('niches')))
            ->map(fn (string $n) => Str::lower(trim($n)))
            ->filter()
            ->values();

        $includeIgnored = (bool) $this->option('include-ignored');
        $ignored = $includeIgnored ? collect() : collect($exclusions->ignoredLabels());

        $configured = collect(config('niches.niches', []))
            ->when($nicheFilter->isNotEmpty(), fn ($c) => $c->filter(
                fn (array $n) => $nicheFilter->contains(Str::lower($n['label']))
            ));

        $niches = $configured
            ->reject(fn (array $n) => $ignored->contains($n['label']))
            ->values();

        $sample = max(1, (int) $this->option('sample'));
        $scanDate = now('Europe/London')->toDateString();
        $force = (bool) $this->option('force');
        $count = 0;
        $skipped = 0;
        $excludedFromRun = $configured->count() - $niches->count();

        foreach ($niches as $niche) {
            foreach ($cities as $city) {
                if (! $force && $this->alreadyComplete($niche['label'], $city, $scanDate)) {
                    $skipped++;

                    continue;
                }

                NicheQueue::dispatch(new ScanNicheJob(
                    niche: $niche['label'],
                    nicheQuery: $niche['query'],
                    city: $city,
                    country: 'GB',
                    sample: $sample,
                    scanDate: $scanDate,
                ));
                $count++;
            }
        }

        $message = "Dispatched {$count} ScanNicheJob(s) for scan_date {$scanDate}.";

        if ($skipped > 0) {
            $message .= " Skipped {$skipped} already complete (use --force to re-run).";
        }

        if (! $includeIgnored && $excludedFromRun > 0) {
            $message .= " Excluded {$excludedFromRun} ignored niche(s).";
        }

        $this->info($message);

        return self::SUCCESS;
    }

    private function alreadyComplete(string $niche, string $city, string $scanDate): bool
    {
        return NicheScan::query()
            ->where('niche', $niche)
            ->where('city', $city)
            ->whereDate('scan_date', $scanDate)
            ->where('status', 'complete')
            ->exists();
    }
}
