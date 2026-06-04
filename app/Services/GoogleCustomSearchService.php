<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleCustomSearchService
{
    /**
     * @return list<array{url: string, title: string, snippet: string}>
     */
    public function search(string $query): array
    {
        $key = config('services.google_cse.key');
        $cx = config('services.google_cse.cx');

        if (! $key || ! $cx) {
            return [];
        }

        $timeout = max(1, (int) config('scanner.website_discovery_timeout_seconds', 8));
        $num = max(1, min(10, (int) config('scanner.website_discovery_num_results', 5)));

        $response = Http::timeout($timeout)
            ->get('https://www.googleapis.com/customsearch/v1', [
                'key' => $key,
                'cx'  => $cx,
                'q'   => $query,
                'num' => $num,
            ]);

        if ($response->failed()) {
            Log::warning('Google CSE request failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return [];
        }

        $items = [];

        foreach ($response->json('items') ?? [] as $item) {
            $url = $item['link'] ?? null;

            if (! is_string($url) || $url === '') {
                continue;
            }

            $items[] = [
                'url'     => $url,
                'title'   => (string) ($item['title'] ?? ''),
                'snippet' => (string) ($item['snippet'] ?? ''),
            ];
        }

        return $items;
    }
}
