<?php

namespace App\Services;

use App\Models\Prospect;
use App\Support\GbpFieldExtractor;
use App\Support\WeakWebsiteHostChecker;

class GbpScoringService
{
    public function __construct(
        private GbpFieldExtractor $fields,
        private GbpAbsoluteScorer $absoluteScorer,
        private GbpRelativeScorer $relativeScorer,
        private WeakWebsiteHostChecker $weakHosts,
    ) {}

    /**
     * Score a raw Places API payload for GBP weakness.
     *
     * @return array{score: int, flags: string[]}
     */
    public function score(array $payload, ?array $benchmark = null, string $city = ''): array
    {
        $absolute = $this->absoluteScorer->score($payload);

        if ($benchmark === null || ($payload['id'] ?? null) === ($benchmark['place_id'] ?? null)) {
            return $this->mergeScores($absolute, ['score' => 0, 'flags' => []]);
        }

        $relative = $this->relativeScorer->score($payload, $benchmark, $city);

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
        return $this->fields->prospectFields($payload);
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

    public function isWeakWebsiteHost(string $uri): bool
    {
        return $this->weakHosts->isWeak($uri);
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
