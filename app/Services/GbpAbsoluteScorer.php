<?php

namespace App\Services;

use App\Support\GbpFieldExtractor;
use App\Support\WeakWebsiteHostChecker;

class GbpAbsoluteScorer
{
    public function __construct(
        private GbpFieldExtractor $fields,
        private WeakWebsiteHostChecker $weakHosts,
    ) {}

    /**
     * @return array{score: int, flags: string[]}
     */
    public function score(array $payload): array
    {
        $score = 0;
        $flags = [];

        $extracted = $this->fields->prospectFields($payload);
        $reviewCount = $extracted['review_count'];
        $photoCount = $extracted['photo_count'];
        $rating = $extracted['rating'];
        $hasWebsite = ! empty($payload['websiteUri']);
        $hasDesc = $extracted['has_description'];
        $hasHours = $extracted['hours_complete'];

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
        } elseif ($this->weakHosts->isWeak((string) $payload['websiteUri'])) {
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
}
