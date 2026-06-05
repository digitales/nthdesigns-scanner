<?php

namespace App\Services;

use App\Services\ApiUsage\ApiUsageGate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BraveSearchService
{
    private const ENDPOINT = 'https://api.search.brave.com/res/v1/web/search';

    public function __construct(
        private ApiUsageGate $usageGate,
    ) {}

    /**
     * @return list<array{url: string, title: string, snippet: string}>
     */
    public function search(string $query, string $country = 'GB'): array
    {
        $token = config('services.brave_search.api_key');

        if (! $token) {
            return [];
        }

        $timeout = max(1, (int) config('scanner.website_discovery_timeout_seconds', 8));
        $count = max(1, min(20, (int) config('scanner.website_discovery_num_results', 5)));
        $country = strtoupper(substr(trim($country), 0, 2)) ?: 'GB';

        $this->usageGate->assertWithinQuota('brave', 'web_search');

        $response = Http::timeout($timeout)
            ->withHeaders([
                'Accept' => 'application/json',
                'Accept-Encoding' => 'gzip',
                'X-Subscription-Token' => $token,
            ])
            ->get(self::ENDPOINT, [
                'q' => $query,
                'count' => $count,
                'country' => $country,
                'search_lang' => 'en',
                'safesearch' => 'moderate',
            ]);

        $this->usageGate->recordCompletedRequest('brave', 'web_search');

        if ($response->failed()) {
            Log::warning('Brave Search request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        $items = [];

        foreach ($response->json('web.results') ?? [] as $result) {
            $url = $result['url'] ?? null;

            if (! is_string($url) || $url === '') {
                continue;
            }

            $items[] = [
                'url' => $url,
                'title' => (string) ($result['title'] ?? ''),
                'snippet' => (string) ($result['description'] ?? ''),
            ];
        }

        return $items;
    }
}
