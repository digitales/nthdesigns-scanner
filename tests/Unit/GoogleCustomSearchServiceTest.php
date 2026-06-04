<?php

namespace Tests\Unit;

use App\Services\GoogleCustomSearchService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleCustomSearchServiceTest extends TestCase
{
    public function test_search_returns_normalised_items(): void
    {
        config([
            'services.google_cse.key' => 'test-key',
            'services.google_cse.cx'  => 'test-cx',
        ]);

        Http::fake([
            'https://www.googleapis.com/customsearch/v1*' => Http::response([
                'items' => [
                    [
                        'link'    => 'https://example.com/page',
                        'title'   => 'Example Co',
                        'snippet' => 'An example business',
                    ],
                ],
            ], 200),
        ]);

        $items = app(GoogleCustomSearchService::class)->search('"Example Co" London');

        $this->assertCount(1, $items);
        $this->assertSame('https://example.com/page', $items[0]['url']);
        $this->assertSame('Example Co', $items[0]['title']);
    }

    public function test_search_returns_empty_on_failure(): void
    {
        config([
            'services.google_cse.key' => 'test-key',
            'services.google_cse.cx'  => 'test-cx',
        ]);

        Http::fake([
            'https://www.googleapis.com/customsearch/v1*' => Http::response([], 500),
        ]);

        $items = app(GoogleCustomSearchService::class)->search('test');

        $this->assertSame([], $items);
    }
}
