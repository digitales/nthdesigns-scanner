<?php

namespace App\Services;

use App\Models\Prospect;
use App\Models\Search;
use App\Support\WebsiteUrlNormalizer;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class WebsiteDiscoveryService
{
    public const GBP_FLAG_NOT_ON_PROFILE = 'Website not listed on Google profile';

    public function __construct(
        private BraveSearchService $brave,
        private GoogleCustomSearchService $googleCse,
        private GbpScoringService $gbpScorer,
        private WebsiteUrlNormalizer $normalizer,
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

        if (! in_array($search->scan_type, ['accessibility_only', 'combined'], true)) {
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
            'host' => $this->normalizer->host($match['url']),
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
        $nameTokens = $this->nameTokens($businessName, 3);
        $mediumTokens = $this->nameTokens($businessName, 4);

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeCandidateUrl($candidate['url']);

            if ($normalized === null) {
                continue;
            }

            if ($this->gbpScorer->isWeakWebsiteHost($normalized)) {
                continue;
            }

            $host = $this->normalizer->host($normalized);
            $haystackTitle = strtolower($candidate['title']);
            $haystackDomain = strtolower($host);

            if ($this->matchesHighTier($nameTokens, $city, $candidate, $haystackDomain, $haystackTitle)) {
                return ['url' => $normalized, 'confidence' => 'high'];
            }
        }

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeCandidateUrl($candidate['url']);

            if ($normalized === null) {
                continue;
            }

            if ($this->gbpScorer->isWeakWebsiteHost($normalized)) {
                continue;
            }

            $host = $this->normalizer->host($normalized);
            $haystackTitle = strtolower($candidate['title']);
            $haystackDomain = strtolower($host);

            if ($this->matchesMediumTier($mediumTokens, $haystackDomain, $haystackTitle)) {
                return ['url' => $normalized, 'confidence' => 'medium'];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $nameTokens
     * @param  array{url: string, title: string, snippet: string}  $candidate
     */
    private function matchesHighTier(
        array $nameTokens,
        string $city,
        array $candidate,
        string $haystackDomain,
        string $haystackTitle,
    ): bool {
        if ($nameTokens === []) {
            return false;
        }

        $nameMatch = false;

        foreach ($nameTokens as $token) {
            if (str_contains($haystackDomain, $token) || str_contains($haystackTitle, $token)) {
                $nameMatch = true;
                break;
            }
        }

        if (! $nameMatch) {
            return false;
        }

        $cityLower = strtolower($city);
        $localityHaystack = strtolower($candidate['title'].' '.$candidate['snippet'].' '.$candidate['url']);

        return str_contains($localityHaystack, $cityLower);
    }

    /**
     * @param  list<string>  $tokens
     */
    private function matchesMediumTier(array $tokens, string $haystackDomain, string $haystackTitle): bool
    {
        foreach ($tokens as $token) {
            if (str_contains($haystackDomain, $token) || str_contains($haystackTitle, $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function nameTokens(string $businessName, int $minLength): array
    {
        $normalized = preg_replace('/\b(ltd|limited|llp|plc|inc)\b\.?/iu', '', $businessName) ?? $businessName;
        $normalized = str_replace('&', ' ', $normalized);
        $parts = preg_split('/\s+/u', trim($normalized)) ?: [];

        $tokens = [];

        foreach ($parts as $part) {
            $token = strtolower(preg_replace('/[^a-z0-9]/i', '', $part) ?? '');

            if (strlen($token) >= $minLength) {
                $tokens[] = $token;
            }
        }

        return array_values(array_unique($tokens));
    }

    private function normalizeCandidateUrl(string $url): ?string
    {
        try {
            $normalized = $this->normalizer->normalize($url);
            $host = $this->normalizer->host($normalized);

            return 'https://'.$host;
        } catch (InvalidArgumentException) {
            return null;
        }
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
