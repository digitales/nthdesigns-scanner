<?php

namespace App\Services;

use App\Enums\ScanType;
use App\Models\Prospect;
use App\Models\Search;
use Illuminate\Support\Facades\Log;

class WebsiteDiscoveryService
{
    public const GBP_FLAG_NOT_ON_PROFILE = 'Website not listed on Google profile';

    public function __construct(
        private BraveSearchService $brave,
        private GoogleCustomSearchService $googleCse,
        private GbpScoringService $gbpScorer,
        private WebsiteDiscoveryMatcher $matcher,
    ) {}

    public function isEnabled(): bool
    {
        if (! config('scanner.website_discovery_enabled', true)) {
            return false;
        }

        return match ($this->provider()) {
            'google_cse' => (bool) config('services.google_cse.key')
                && (bool) config('services.google_cse.cx'),
            default => (bool) config('services.brave_search.api_key'),
        };
    }

    private function provider(): string
    {
        $provider = (string) config('scanner.website_discovery_provider', 'brave');

        return $provider === 'google_cse' ? 'google_cse' : 'brave';
    }

    /**
     * @return list<array{url: string, title: string, snippet: string}>
     */
    private function searchWeb(string $query, Search $search): array
    {
        $country = strtoupper(substr(trim((string) $search->country), 0, 2)) ?: 'GB';

        return match ($this->provider()) {
            'google_cse' => $this->googleCse->search($query),
            default => $this->brave->search($query, $country),
        };
    }

    public function shouldDiscover(Search $search, array $gbpPayload): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        if (! in_array($search->scan_type, [ScanType::AccessibilityOnly, ScanType::Combined], true)) {
            return false;
        }

        if (! empty($gbpPayload['websiteUri'])) {
            return false;
        }

        $city = trim((string) $search->city);

        return $city !== '';
    }

    /**
     * @param  array<string, mixed>  $gbpPayload
     * @return array{url: string, confidence: string}|null
     */
    public function discover(Prospect $prospect, Search $search, array $gbpPayload): ?array
    {
        if (! $this->shouldDiscover($search, $gbpPayload)) {
            $this->logSkipped($search, $prospect, 'disabled');

            return null;
        }

        $query = '"'.$prospect->business_name.'" '.$search->city;
        $candidates = $this->searchWeb($query, $search);

        if ($candidates === []) {
            $this->logSkipped($search, $prospect, 'no_results');

            return null;
        }

        $match = $this->matchCandidates(
            $candidates,
            $prospect->business_name,
            (string) $search->city,
        );

        if ($match === null) {
            $this->logSkipped($search, $prospect, 'no_match', [
                'candidates_tried' => count($candidates),
            ]);

            return null;
        }

        Log::info('website_discovery.matched', [
            'search_id' => $search->id,
            'place_id' => $prospect->place_id,
            'confidence' => $match['confidence'],
            'host' => parse_url($match['url'], PHP_URL_HOST),
        ]);

        return $match;
    }

    public function isBackfillCandidate(Prospect $prospect, Search $search): bool
    {
        if (! empty($prospect->website_url)) {
            return false;
        }

        return $this->shouldDiscover($search, $prospect->raw_gbp_payload ?? []);
    }

    /**
     * Persist a discovery match: URL provenance, GBP rescore, combined score.
     *
     * @param  array{url: string, confidence: string}  $match
     */
    public function applyMatch(Prospect $prospect, Search $search, array $match): Prospect
    {
        $payload = $prospect->raw_gbp_payload ?? [];
        $discoveredAt = now();

        $overlayProspect = new Prospect([
            'website_url' => $match['url'],
        ]);

        $overlay = $this->gbpScorer->overlayProspectFields($payload, $overlayProspect);
        $scored = $this->gbpScorer->score(
            $overlay,
            $search->benchmark_snapshot,
            $search->city ?? '',
        );

        $flags = $scored['flags'];

        if (! in_array(self::GBP_FLAG_NOT_ON_PROFILE, $flags, true)) {
            $flags[] = self::GBP_FLAG_NOT_ON_PROFILE;
        }

        $prospect->fill([
            'website_url' => $match['url'],
            'website_url_source' => $this->provider(),
            'website_discovery_confidence' => $match['confidence'],
            'website_discovered_at' => $discoveredAt,
            'gbp_score' => $scored['score'],
            'gbp_flags' => $flags,
        ]);

        $combined = app(CombineScoresService::class)->combineForProspect($prospect, $search->scan_type);
        $prospect->fill($combined);
        $prospect->save();

        return $prospect->fresh();
    }

    /**
     * @param  list<array{url: string, title: string, snippet: string}>  $candidates
     * @return array{url: string, confidence: string}|null
     */
    public function matchCandidates(array $candidates, string $businessName, string $city): ?array
    {
        return $this->matcher->match($candidates, $businessName, $city);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function logSkipped(Search $search, Prospect $prospect, string $reason, array $extra = []): void
    {
        Log::info('website_discovery.skipped', array_merge([
            'search_id' => $search->id,
            'place_id' => $prospect->place_id,
            'reason' => $reason,
        ], $extra));
    }
}
