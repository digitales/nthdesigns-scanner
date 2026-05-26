<?php

namespace App\Services;

class GbpScoringService
{
    /**
     * Score a raw Places API payload for GBP weakness.
     * Returns ['score' => int (0-100), 'flags' => string[]]
     * Higher score = weaker profile = better prospect.
     */
    public function score(array $payload): array
    {
        $score = 0;
        $flags = [];

        $reviewCount  = $payload['userRatingCount'] ?? 0;
        $rating       = $payload['rating'] ?? null;
        $photos       = $payload['photos'] ?? [];
        $photoCount   = count($photos);
        $hasWebsite   = !empty($payload['websiteUri']);
        $hasDesc      = !empty($payload['editorialSummary']['text']);
        $hasHours     = !empty($payload['regularOpeningHours']['periods']);

        // Review count (max 25 pts)
        if ($reviewCount < 20) {
            $score += 25;
            $flags[] = 'Under 20 reviews';
        } elseif ($reviewCount <= 50) {
            $score += 15;
            $flags[] = 'Fewer than 50 reviews';
        }

        // Photos (max 15 pts)
        if ($photoCount === 0) {
            $score += 15;
            $flags[] = 'No photos uploaded';
        } elseif ($photoCount < 5) {
            $score += 8;
            $flags[] = 'Fewer than 5 photos';
        }

        // Website (10 pts)
        if (!$hasWebsite) {
            $score += 10;
            $flags[] = 'No website listed';
        }

        // Description (10 pts)
        if (!$hasDesc) {
            $score += 10;
            $flags[] = 'Missing business description';
        }

        // Hours (10 pts)
        if (!$hasHours) {
            $score += 10;
            $flags[] = 'Opening hours not set';
        }

        // Low rating (10 pts)
        if ($rating !== null && $rating < 3.5) {
            $score += 10;
            $flags[] = 'Rating below 3.5 stars';
        }

        return [
            'score' => min($score, 100),
            'flags' => $flags,
        ];
    }

    /**
     * Extract normalised fields from a raw Places payload.
     */
    public function extractFields(array $payload): array
    {
        return [
            'business_name'   => $payload['displayName']['text'] ?? 'Unknown',
            'phone'           => $payload['nationalPhoneNumber'] ?? null,
            'website_url'     => $payload['websiteUri'] ?? null,
            'address'         => $payload['formattedAddress'] ?? null,
            'rating'          => $payload['rating'] ?? null,
            'review_count'    => $payload['userRatingCount'] ?? 0,
            'photo_count'     => count($payload['photos'] ?? []),
            'has_description' => !empty($payload['editorialSummary']['text']),
            'hours_complete'  => !empty($payload['regularOpeningHours']['periods']),
        ];
    }
}
