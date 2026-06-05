<?php

namespace App\Services;

use App\Support\GbpFieldExtractor;

class GbpRelativeScorer
{
    public function __construct(private GbpFieldExtractor $fields) {}

    /**
     * @return array{score: int, flags: string[]}
     */
    public function score(array $payload, array $benchmark, string $city): array
    {
        $extracted = $this->fields->prospectFields($payload);
        $score = 0;
        $flags = [];

        $prospectReviews = $extracted['review_count'];
        $leaderReviews = (int) $benchmark['review_count'];

        if ($leaderReviews >= 20) {
            $gap = max(0, $leaderReviews - $prospectReviews);
            if ($gap >= 25 || $prospectReviews < 0.5 * $leaderReviews) {
                $score += 15;
                $flags[] = "{$prospectReviews} reviews vs {$leaderReviews} for the top listing in {$city}";
            }
        }

        $photoGap = max(0, (int) $benchmark['photo_count'] - $extracted['photo_count']);
        if ($photoGap >= 5) {
            $score += 10;
            $flags[] = "Fewer photos than top local listing ({$extracted['photo_count']} vs {$benchmark['photo_count']})";
        }

        if (! $extracted['has_description'] && ($benchmark['has_description'] ?? false)) {
            $score += 8;
            $flags[] = "No description while top listing in {$city} has one";
        }

        if (! $extracted['hours_complete'] && ($benchmark['hours_complete'] ?? false)) {
            $score += 8;
            $flags[] = "Hours incomplete vs top listing in {$city}";
        }

        if ($extracted['rating'] !== null && $benchmark['rating'] !== null) {
            $gap = (float) $benchmark['rating'] - (float) $extracted['rating'];
            if ($gap >= 0.3) {
                $score += 8;
                $flags[] = sprintf(
                    'Lower rating than top listing in %s (%s vs %s)',
                    $city,
                    number_format((float) $extracted['rating'], 1),
                    number_format((float) $benchmark['rating'], 1),
                );
            }
        }

        return ['score' => $score, 'flags' => $flags];
    }
}
