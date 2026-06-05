<?php

namespace App\Support;

final class GbpFieldExtractor
{
    /**
     * @return array{
     *     business_name: string,
     *     phone: string|null,
     *     website_url: string|null,
     *     address: string|null,
     *     rating: float|null,
     *     review_count: int,
     *     photo_count: int,
     *     has_description: bool,
     *     hours_complete: bool
     * }
     */
    public function prospectFields(array $payload): array
    {
        return [
            'business_name' => $payload['displayName']['text'] ?? 'Unknown',
            'phone' => $payload['nationalPhoneNumber'] ?? null,
            'website_url' => $payload['websiteUri'] ?? null,
            'address' => $payload['formattedAddress'] ?? null,
            'rating' => isset($payload['rating']) ? (float) $payload['rating'] : null,
            'review_count' => (int) ($payload['userRatingCount'] ?? 0),
            'photo_count' => count($payload['photos'] ?? []),
            'has_description' => ! empty($payload['editorialSummary']['text'] ?? null),
            'hours_complete' => ! empty($payload['regularOpeningHours']['periods'] ?? null),
        ];
    }

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
    public function benchmarkFields(array $place): array
    {
        $fields = $this->prospectFields($place);

        return [
            'place_id' => $place['id'] ?? null,
            'name' => $fields['business_name'] === 'Unknown'
                ? 'Top local competitor'
                : $fields['business_name'],
            'rating' => $fields['rating'],
            'review_count' => $fields['review_count'],
            'photo_count' => $fields['photo_count'],
            'has_description' => $fields['has_description'],
            'hours_complete' => $fields['hours_complete'],
        ];
    }
}
