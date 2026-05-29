<?php

namespace App\Jobs;

use App\Models\NicheScan;
use App\Services\NicheExclusionService;
use App\Services\NicheSampleCollector;
use App\Support\NicheQueue;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ScanNicheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120];

    public function __construct(
        public string $niche,
        public string $nicheQuery,
        public string $city,
        public string $country,
        public int $sample,
        public string $scanDate,
    ) {
        NicheQueue::apply($this);
    }

    public function handle(NicheSampleCollector $collector, NicheExclusionService $exclusions): void
    {
        $scan = $this->pendingScan();

        $result = $collector->collect(
            $this->nicheQuery,
            $this->city,
            $this->country,
            $this->sample,
        );

        $this->markComplete($scan, $result);

        $exclusions->refreshForNiche($this->niche);
    }

    private function pendingScan(): NicheScan
    {
        return NicheScan::query()->updateOrCreate(
            [
                'niche' => $this->niche,
                'city' => $this->city,
                'scan_date' => Carbon::parse($this->scanDate)->toDateString(),
            ],
            [
                'niche_query' => $this->nicheQuery,
                'country' => $this->country,
                'status' => 'pending',
            ],
        );
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('ScanNicheJob failed', [
            'niche' => $this->niche,
            'city' => $this->city,
            'scan_date' => $this->scanDate,
            'error' => $exception?->getMessage(),
        ]);

        NicheScan::query()
            ->where('niche', $this->niche)
            ->where('city', $this->city)
            ->whereDate('scan_date', $this->scanDate)
            ->update(['status' => 'failed']);
    }

    public static function opportunityScore(
        float $avgGbp,
        float $pctNoWebsite,
        float $pctLowReviews,
        int $resultCount,
    ): float {
        if ($resultCount <= 1) {
            return 0.0;
        }

        $raw = ($avgGbp * 0.4) + ($pctNoWebsite * 0.35) + ($pctLowReviews * 0.25);

        if ($resultCount === 2) {
            $raw *= 0.5;
        }

        return round($raw, 2);
    }

    /**
     * @param  array{
     *     result_count: int,
     *     sampled_count: int,
     *     avg_gbp_score: float,
     *     pct_no_website: float,
     *     pct_low_reviews: float,
     *     opportunity_score: float,
     *     sample_preview: array<int, array{name: string, gbp_score: int, no_website: bool, review_count: int}>
     * }  $metrics
     */
    private function markComplete(NicheScan $scan, array $metrics): void
    {
        $scan->update([
            ...$metrics,
            'status' => 'complete',
            'ran_at' => now(),
        ]);
    }
}
