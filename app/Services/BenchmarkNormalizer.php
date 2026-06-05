<?php

namespace App\Services;

class BenchmarkNormalizer
{
    /**
     * @return array{
     *     place_id: string|null,
     *     name: string,
     *     review_count: int,
     *     photo_count: int,
     *     rating: float|null,
     *     has_description: bool,
     *     hours_complete: bool
     * }
     */
    public function fromPlace(array $place): array
    {
        return [
            'place_id' => $place['id'] ?? null,
            'name' => $place['displayName']['text'] ?? 'Top local competitor',
            'rating' => isset($place['rating']) ? (float) $place['rating'] : null,
            'review_count' => (int) ($place['userRatingCount'] ?? 0),
            'photo_count' => count($place['photos'] ?? []),
            'has_description' => ! empty($place['editorialSummary']['text'] ?? null),
            'hours_complete' => ! empty($place['regularOpeningHours']['periods'] ?? null),
        ];
    }
}
