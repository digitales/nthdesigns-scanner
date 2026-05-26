<?php

namespace App\Services;

use App\Models\Prospect;

class ReportBuilderService
{
    /**
     * Build structured report payload for storage and public display.
     *
     * @param  array<string, mixed>|null  $benchmarkPlace  Raw Places API place object
     * @return array<string, mixed>
     */
    public function build(Prospect $prospect, ?array $benchmarkPlace = null): array
    {
        $search = $prospect->search;

        $benchmark = null;

        if ($benchmarkPlace) {
            $benchmark = [
                'place_id'     => $benchmarkPlace['id'] ?? null,
                'name'         => $benchmarkPlace['displayName']['text'] ?? 'Top local competitor',
                'rating'       => $benchmarkPlace['rating'] ?? null,
                'review_count' => $benchmarkPlace['userRatingCount'] ?? 0,
                'photo_count'  => count($benchmarkPlace['photos'] ?? []),
            ];
        }

        $comparison = [];

        if ($benchmark) {
            $comparison = [
                'review_gap'  => max(0, ($benchmark['review_count'] ?? 0) - $prospect->review_count),
                'photo_gap'   => max(0, ($benchmark['photo_count'] ?? 0) - $prospect->photo_count),
                'rating_gap'  => $benchmark['rating'] && $prospect->rating
                    ? round((float) $benchmark['rating'] - (float) $prospect->rating, 1)
                    : null,
            ];
        }

        return [
            'niche'        => $search->niche,
            'city'         => $search->city,
            'country'      => $search->country,
            'scan_type'    => $search->scan_type,
            'booking_url'  => config('scanner.report_booking_url'),
            'generated_at' => now()->toIso8601String(),
            'prospect'     => [
                'business_name'     => $prospect->business_name,
                'address'           => $prospect->address,
                'phone'             => $prospect->phone,
                'website_url'       => $prospect->website_url,
                'rating'            => $prospect->rating,
                'review_count'      => $prospect->review_count,
                'photo_count'       => $prospect->photo_count,
                'has_description'   => $prospect->has_description,
                'hours_complete'    => $prospect->hours_complete,
                'gbp_score'         => $prospect->gbp_score,
                'gbp_flags'         => $prospect->gbp_flags ?? [],
                'a11y_score'        => $prospect->a11y_score,
                'a11y_flags'        => $prospect->a11y_flags ?? [],
                'performance_score' => $prospect->performance_score,
                'combined_score'    => $prospect->combined_score,
                'dominant_angle'    => $prospect->dominant_angle,
            ],
            'benchmark'  => $benchmark,
            'comparison' => $comparison,
        ];
    }
}
