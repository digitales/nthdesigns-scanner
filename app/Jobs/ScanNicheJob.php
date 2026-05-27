<?php

namespace App\Jobs;

use App\Models\NicheScan;
use App\Services\GbpScoringService;
use App\Services\GooglePlacesService;
use App\Support\ScrapingQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;
use Illuminate\Support\Arr;
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
        ScrapingQueue::apply($this);
    }

    public function handle(GooglePlacesService $places, GbpScoringService $scorer): void
    {
        $scan = $this->pendingScan();

        $placeIds = $places->searchByNicheAndCity($this->nicheQuery, $this->city, $this->country);
        $resultCount = count($placeIds);

        if ($resultCount === 0) {
            $this->markComplete($scan, [
                'result_count' => 0,
                'sampled_count' => 0,
                'avg_gbp_score' => 0,
                'pct_no_website' => 0,
                'pct_low_reviews' => 0,
                'opportunity_score' => 0,
            ]);

            return;
        }

        $sampleSize = min($this->sample, $resultCount);
        $sampleIds = Arr::random($placeIds, $sampleSize);
        $sampleIds = is_array($sampleIds) ? $sampleIds : [$sampleIds];

        $scores = [];
        $noWebsite = 0;
        $lowReviews = 0;
        $sampled = 0;

        foreach ($sampleIds as $placeId) {
            $payload = $places->getPlaceDetails($placeId);

            if (! $payload) {
                continue;
            }

            $sampled++;
            $scored = $scorer->score($payload, null);
            $scores[] = $scored['score'];

            if (empty($payload['websiteUri'])) {
                $noWebsite++;
            }

            if ((int) ($payload['userRatingCount'] ?? 0) < 20) {
                $lowReviews++;
            }
        }

        if ($sampled === 0) {
            $this->markComplete($scan, [
                'result_count' => $resultCount,
                'sampled_count' => 0,
                'avg_gbp_score' => 0,
                'pct_no_website' => 0,
                'pct_low_reviews' => 0,
                'opportunity_score' => 0,
            ]);

            return;
        }

        $avg = array_sum($scores) / $sampled;
        $pctNoWebsite = ($noWebsite / $sampled) * 100;
        $pctLowReviews = ($lowReviews / $sampled) * 100;

        $this->markComplete($scan, [
            'result_count' => $resultCount,
            'sampled_count' => $sampled,
            'avg_gbp_score' => round($avg, 2),
            'pct_no_website' => round($pctNoWebsite, 2),
            'pct_low_reviews' => round($pctLowReviews, 2),
            'opportunity_score' => self::opportunityScore($avg, $pctNoWebsite, $pctLowReviews),
        ]);
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

    public static function opportunityScore(float $avgGbp, float $pctNoWebsite, float $pctLowReviews): float
    {
        return round(($avgGbp * 0.4) + ($pctNoWebsite * 0.35) + ($pctLowReviews * 0.25), 2);
    }

    /**
     * @param  array{
     *     result_count: int,
     *     sampled_count: int,
     *     avg_gbp_score: float,
     *     pct_no_website: float,
     *     pct_low_reviews: float,
     *     opportunity_score: float
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
