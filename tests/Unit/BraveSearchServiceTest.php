<?php

namespace Tests\Unit;

use App\Services\BraveSearchService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BraveSearchServiceTest extends TestCase
{
    public function test_search_returns_normalised_items(): void
    {
        config(['services.brave_search.api_key' => 'test-token']);

        Http::fake([
            'https://api.search.brave.com/res/v1/web/search*' => Http::response([
                'web' => [
                    'results' => [
                        [
                            'url' => 'https://example.com/page',
                            'title' => 'Example Co',
                            'description' => 'An example business in London',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $items = app(BraveSearchService::class)->search('"Example Co" London', 'GB');

        $this->assertCount(1, $items);
        $this->assertSame('https://example.com/page', $items[0]['url']);
        $this->assertSame('Example Co', $items[0]['title']);
        $this->assertSame('An example business in London', $items[0]['snippet']);

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Subscription-Token', 'test-token')
                && str_contains($request->url(), 'country=GB')
                && str_contains($request->url(), 'count=5');
        });
    }

    public function test_search_returns_empty_on_failure(): void
    {
        config(['services.brave_search.api_key' => 'test-token']);

        Http::fake([
            'https://api.search.brave.com/res/v1/web/search*' => Http::response([], 500),
        ]);

        $items = app(BraveSearchService::class)->search('test');

        $this->assertSame([], $items);
    }

    public function test_search_returns_empty_without_api_key(): void
    {
        config(['services.brave_search.api_key' => null]);

        Http::fake();

        $items = app(BraveSearchService::class)->search('test');

        $this->assertSame([], $items);
        Http::assertNothingSent();
    }
}
