<?php

namespace App\Services;

use App\Models\Prospect;

class GbpScoringService
{
    /**
     * Score a raw Places API payload for GBP weakness.
     *
     * @return array{score: int, flags: string[]}
     */
    public function score(array $payload, ?array $benchmark = null, string $city = ''): array
    {
        $absolute = $this->scoreAbsolute($payload);

        if ($benchmark === null || ($payload['id'] ?? null) === ($benchmark['place_id'] ?? null)) {
            return $this->mergeScores($absolute, ['score' => 0, 'flags' => []]);
        }

        $relative = $this->scoreRelative($payload, $benchmark, $city);

        return $this->mergeScores($absolute, $relative);
    }

    /**
     * Extract normalised fields from a raw Places payload.
     *
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
    public function extractFields(array $payload): array
    {
        return [
            'business_name'   => $payload['displayName']['text'] ?? 'Unknown',
            'phone'           => $payload['nationalPhoneNumber'] ?? null,
            'website_url'     => $payload['websiteUri'] ?? null,
            'address'         => $payload['formattedAddress'] ?? null,
            'rating'          => isset($payload['rating']) ? (float) $payload['rating'] : null,
            'review_count'    => (int) ($payload['userRatingCount'] ?? 0),
            'photo_count'     => count($payload['photos'] ?? []),
            'has_description' => ! empty($payload['editorialSummary']['text'] ?? null),
            'hours_complete'  => ! empty($payload['regularOpeningHours']['periods'] ?? null),
        ];
    }

    /**
     * @return array{score: int, flags: string[]}
     */
    private function scoreAbsolute(array $payload): array
    {
        $score = 0;
        $flags = [];

        $fields = $this->extractFields($payload);
        $reviewCount = $fields['review_count'];
        $photoCount = $fields['photo_count'];
        $rating = $fields['rating'];
        $hasWebsite = ! empty($payload['websiteUri']);
        $hasDesc = $fields['has_description'];
        $hasHours = $fields['hours_complete'];

        if ($reviewCount < 20) {
            $score += 25;
            $flags[] = 'Under 20 reviews';
        } elseif ($reviewCount <= 50) {
            $score += 15;
            $flags[] = 'Fewer than 50 reviews';
        }

        if ($photoCount === 0) {
            $score += 15;
            $flags[] = 'No photos uploaded';
        } elseif ($photoCount < 5) {
            $score += 8;
            $flags[] = 'Fewer than 5 photos';
        } elseif ($photoCount < 10) {
            $score += 5;
            $flags[] = 'Fewer than 10 photos';
        }

        if (! $hasWebsite) {
            $score += 10;
            $flags[] = 'No website listed';
        } elseif ($this->isWeakWebsiteHost((string) $payload['websiteUri'])) {
            $score += 8;
            $flags[] = 'No dedicated website';
        }

        if (! $hasDesc) {
            $score += 10;
            $flags[] = 'Missing business description';
        }

        if (! $hasHours) {
            $score += 10;
            $flags[] = 'Opening hours not set';
        }

        if (empty($payload['nationalPhoneNumber'])) {
            $score += 8;
            $flags[] = 'No phone number listed';
        }

        if ($rating !== null && $rating < 3.5) {
            $score += 10;
            $flags[] = 'Rating below 3.5 stars';
        } elseif ($rating !== null && $rating < 4.0) {
            $score += 5;
            $flags[] = 'Rating below 4 stars';
        }

        if (isset($payload['businessStatus']) && $payload['businessStatus'] !== 'OPERATIONAL') {
            $score += 15;
            $flags[] = 'Listing not fully operational';
        }

        return ['score' => $score, 'flags' => $flags];
    }

    /**
     * @return array{score: int, flags: string[]}
     */
    private function scoreRelative(array $payload, array $benchmark, string $city): array
    {
        $fields = $this->extractFields($payload);
        $score = 0;
        $flags = [];

        $prospectReviews = $fields['review_count'];
        $leaderReviews = (int) $benchmark['review_count'];

        if ($leaderReviews >= 20) {
            $gap = max(0, $leaderReviews - $prospectReviews);
            if ($gap >= 25 || $prospectReviews < 0.5 * $leaderReviews) {
                $score += 15;
                $flags[] = "{$prospectReviews} reviews vs {$leaderReviews} for the top listing in {$city}";
            }
        }

        $photoGap = max(0, (int) $benchmark['photo_count'] - $fields['photo_count']);
        if ($photoGap >= 5) {
            $score += 10;
            $flags[] = "Fewer photos than top local listing ({$fields['photo_count']} vs {$benchmark['photo_count']})";
        }

        if (! $fields['has_description'] && ($benchmark['has_description'] ?? false)) {
            $score += 8;
            $flags[] = "No description while top listing in {$city} has one";
        }

        if (! $fields['hours_complete'] && ($benchmark['hours_complete'] ?? false)) {
            $score += 8;
            $flags[] = "Hours incomplete vs top listing in {$city}";
        }

        if ($fields['rating'] !== null && $benchmark['rating'] !== null) {
            $gap = (float) $benchmark['rating'] - (float) $fields['rating'];
            if ($gap >= 0.3) {
                $score += 8;
                $flags[] = sprintf(
                    'Lower rating than top listing in %s (%s vs %s)',
                    $city,
                    number_format((float) $fields['rating'], 1),
                    number_format((float) $benchmark['rating'], 1),
                );
            }
        }

        return ['score' => $score, 'flags' => $flags];
    }

    /**
     * @param  array{score: int, flags: string[]}  $absolute
     * @param  array{score: int, flags: string[]}  $relative
     * @return array{score: int, flags: string[]}
     */
    private function mergeScores(array $absolute, array $relative): array
    {
        $absoluteFlags = $absolute['flags'];
        $relativeFlags = $relative['flags'];
        $relativeScore = $relative['score'];

        if (in_array('Missing business description', $absoluteFlags, true)) {
            foreach ($relativeFlags as $index => $flag) {
                if (str_starts_with($flag, 'No description while top listing')) {
                    unset($relativeFlags[$index]);
                    $relativeScore = max(0, $relativeScore - 8);
                }
            }
        }

        if (in_array('Opening hours not set', $absoluteFlags, true)) {
            foreach ($relativeFlags as $index => $flag) {
                if (str_starts_with($flag, 'Hours incomplete vs top listing')) {
                    unset($relativeFlags[$index]);
                    $relativeScore = max(0, $relativeScore - 8);
                }
            }
        }

        $flags = array_merge($absoluteFlags, array_values($relativeFlags));
        $score = min($absolute['score'] + $relativeScore, 100);

        return ['score' => $score, 'flags' => $flags];
    }

    private function isWeakWebsiteHost(string $uri): bool
    {
        $host = strtolower((string) parse_url($uri, PHP_URL_HOST));

        if ($host === '') {
            return false;
        }

        $needles = [
            'facebook.com',
            'fb.com',
            'instagram.com',
            'linktr.ee',
            'tiktok.com',
            'twitter.com',
            'x.com',
            'yelp.',
            'wixsite.com',
            'square.site',
            'godaddysites.com',
            'google.site',
            'sites.google.com',
        ];

        foreach ($needles as $needle) {
            if (str_contains($host, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apply operator-edited phone/website onto a Places payload for rescoring.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function overlayProspectFields(array $payload, Prospect $prospect): array
    {
        if ($prospect->website_url !== null) {
            if ($prospect->website_url === '') {
                unset($payload['websiteUri']);
            } else {
                $payload['websiteUri'] = $prospect->website_url;
            }
        }

        if ($prospect->phone !== null) {
            if ($prospect->phone === '') {
                unset($payload['nationalPhoneNumber']);
            } else {
                $payload['nationalPhoneNumber'] = $prospect->phone;
            }
        }

        return $payload;
    }

    /**
     * @return array{score: int, flags: string[]}
     */
    public function scoreProspect(Prospect $prospect): array
    {
        $search = $prospect->search;
        $payload = $this->overlayProspectFields($prospect->raw_gbp_payload ?? [], $prospect);

        return $this->score(
            $payload,
            $search?->benchmark_snapshot,
            $search?->city ?? '',
        );
    }
}
