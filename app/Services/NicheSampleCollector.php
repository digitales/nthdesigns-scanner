<?php

namespace App\Services;

use App\Jobs\ScanNicheJob;
use Illuminate\Support\Arr;

final class NicheSampleCollector
{
    public function __construct(
        private GooglePlacesService $places,
        private GbpScoringService $scorer,
    ) {}

    /**
     * @return array{
     *     result_count: int,
     *     sampled_count: int,
     *     avg_gbp_score: float,
     *     pct_no_website: float,
     *     pct_low_reviews: float,
     *     opportunity_score: float,
     *     sample_preview: array<int, array{name: string, gbp_score: int, no_website: bool, review_count: int}>
     * }
     */
    public function collect(string $nicheQuery, string $city, string $country, int $sample): array
    {
        $placeIds = $this->places->searchByNicheAndCity($nicheQuery, $city, $country);
        $resultCount = count($placeIds);

        if ($resultCount === 0) {
            return [
                'result_count' => 0,
                'sampled_count' => 0,
                'avg_gbp_score' => 0,
                'pct_no_website' => 0,
                'pct_low_reviews' => 0,
                'opportunity_score' => 0,
                'sample_preview' => [],
            ];
        }

        $sampleSize = min($sample, $resultCount);
        $sampleIds = Arr::random($placeIds, $sampleSize);
        $sampleIds = is_array($sampleIds) ? $sampleIds : [$sampleIds];

        $scores = [];
        $preview = [];
        $noWebsite = 0;
        $lowReviews = 0;
        $sampled = 0;

        foreach ($sampleIds as $placeId) {
            $payload = $this->places->getPlaceDetails($placeId);

            if (! $payload) {
                continue;
            }

            $sampled++;
            $scored = $this->scorer->score($payload, null);
            $scores[] = $scored['score'];

            $reviewCount = (int) ($payload['userRatingCount'] ?? 0);
            $hasNoWebsite = empty($payload['websiteUri']);

            if ($hasNoWebsite) {
                $noWebsite++;
            }

            if ($reviewCount < 20) {
                $lowReviews++;
            }

            $preview[] = [
                'name' => $payload['displayName']['text'] ?? 'Unknown',
                'gbp_score' => (int) round($scored['score']),
                'no_website' => $hasNoWebsite,
                'review_count' => $reviewCount,
            ];
        }

        if ($sampled === 0) {
            return [
                'result_count' => $resultCount,
                'sampled_count' => 0,
                'avg_gbp_score' => 0,
                'pct_no_website' => 0,
                'pct_low_reviews' => 0,
                'opportunity_score' => 0,
                'sample_preview' => [],
            ];
        }

        $avg = array_sum($scores) / $sampled;
        $pctNoWebsite = ($noWebsite / $sampled) * 100;
        $pctLowReviews = ($lowReviews / $sampled) * 100;

        return [
            'result_count' => $resultCount,
            'sampled_count' => $sampled,
            'avg_gbp_score' => round($avg, 2),
            'pct_no_website' => round($pctNoWebsite, 2),
            'pct_low_reviews' => round($pctLowReviews, 2),
            'opportunity_score' => ScanNicheJob::opportunityScore($avg, $pctNoWebsite, $pctLowReviews),
            'sample_preview' => $preview,
        ];
    }
}
