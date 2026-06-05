<?php

namespace Tests\Unit;

use App\Services\BraveSearchService;
use App\Services\GbpScoringService;
use App\Services\GoogleCustomSearchService;
use App\Services\WebsiteDiscoveryService;
use App\Support\WebsiteUrlNormalizer;
use Tests\TestCase;

class WebsiteDiscoveryServiceTest extends TestCase
{
    private WebsiteDiscoveryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new WebsiteDiscoveryService(
            new BraveSearchService,
            new GoogleCustomSearchService,
            new GbpScoringService,
            new WebsiteUrlNormalizer,
        );
    }

    public function test_high_confidence_match_requires_name_and_city(): void
    {
        $match = $this->service->matchCandidates(
            [
                [
                    'url' => 'https://briarwren.co.uk/about',
                    'title' => 'Briar & Wren Solicitors — Manchester',
                    'snippet' => 'Legal services in Manchester city centre.',
                ],
            ],
            'Briar & Wren Solicitors Ltd',
            'Manchester',
        );

        $this->assertNotNull($match);
        $this->assertSame('https://briarwren.co.uk', $match['url']);
        $this->assertSame('high', $match['confidence']);
    }

    public function test_medium_confidence_match_without_city_in_snippet(): void
    {
        $match = $this->service->matchCandidates(
            [
                [
                    'url' => 'https://briarwrenlegal.com',
                    'title' => 'Briar Wren Legal Services',
                    'snippet' => 'Contact our team today.',
                ],
            ],
            'Briar & Wren Solicitors Ltd',
            'Manchester',
        );

        $this->assertNotNull($match);
        $this->assertSame('medium', $match['confidence']);
    }

    public function test_rejects_weak_hosts(): void
    {
        $match = $this->service->matchCandidates(
            [
                [
                    'url' => 'https://www.facebook.com/briarwren',
                    'title' => 'Briar & Wren — Manchester',
                    'snippet' => 'Manchester solicitors',
                ],
            ],
            'Briar & Wren',
            'Manchester',
        );

        $this->assertNull($match);
    }

    public function test_no_match_when_name_tokens_absent(): void
    {
        $match = $this->service->matchCandidates(
            [
                [
                    'url' => 'https://unrelated-example.co.uk',
                    'title' => 'Unrelated Business',
                    'snippet' => 'Based in Manchester',
                ],
            ],
            'Briar & Wren',
            'Manchester',
        );

        $this->assertNull($match);
    }
}
