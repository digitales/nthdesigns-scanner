<?php

namespace App\Console\Commands;

use App\Jobs\ScanNicheJob;
use App\Support\NicheQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ScanNichesCommand extends Command
{
    protected $signature = 'niches:scan
        {--cities=}
        {--niches=}
        {--sample=5}';

    protected $description = 'Dispatch niche×city GBP sample scans to the niches queue';

    public function handle(): int
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

        $niches = collect(config('niches.niches', []))
            ->when($nicheFilter->isNotEmpty(), fn ($c) => $c->filter(
                fn (array $n) => $nicheFilter->contains(Str::lower($n['label']))
            ));

        $sample = max(1, (int) $this->option('sample'));
        $scanDate = now('Europe/London')->toDateString();
        $count = 0;

        foreach ($niches as $niche) {
            foreach ($cities as $city) {
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

        $this->info("Dispatched {$count} ScanNicheJob(s) for scan_date {$scanDate}.");

        return self::SUCCESS;
    }
}
