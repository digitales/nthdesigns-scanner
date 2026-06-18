<?php

namespace Tests\Unit;

use App\Models\Prospect;
use App\Services\ProspectValidationSignalMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProspectValidationSignalMatcherTest extends TestCase
{
    use RefreshDatabase;

    private ProspectValidationSignalMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->matcher = app(ProspectValidationSignalMatcher::class);
    }

    public function test_matches_pattern_in_business_name(): void
    {
        $prospect = Prospect::factory()->create([
            'business_name' => 'Smileworks Dental Manchester',
        ]);

        $signals = collect([[
            'pattern' => 'smileworks',
            'source' => 'operator',
            'signal_id' => 1,
            'label' => 'Smileworks',
        ]]);

        $match = $this->matcher->match($prospect, $signals, ['business_name']);

        $this->assertSame('smileworks', $match['pattern']);
        $this->assertSame('business_name', $match['field']);
    }

    public function test_matches_pattern_in_qualification_flags(): void
    {
        $prospect = Prospect::factory()->create([
            'qualification_flags' => ['Footer mentions part of mydentist group'],
        ]);

        $signals = collect([[
            'pattern' => 'mydentist',
            'source' => 'config',
            'signal_id' => null,
            'label' => null,
        ]]);

        $match = $this->matcher->match($prospect, $signals, ['qualification_flags']);

        $this->assertSame('qualification_flags', $match['field']);
    }

    public function test_matches_pattern_in_website_url_without_scheme(): void
    {
        $prospect = Prospect::factory()->create([
            'website_url' => 'https://book.hsone.app/example',
        ]);

        $signals = collect([[
            'pattern' => 'hsone',
            'source' => 'config',
            'signal_id' => null,
            'label' => null,
        ]]);

        $match = $this->matcher->match($prospect, $signals, ['website_url']);

        $this->assertSame('website_url', $match['field']);
    }

    public function test_returns_null_when_no_match(): void
    {
        $prospect = Prospect::factory()->create([
            'business_name' => 'Independent Family Dental',
        ]);

        $signals = collect([[
            'pattern' => 'portman',
            'source' => 'config',
            'signal_id' => null,
            'label' => null,
        ]]);

        $match = $this->matcher->match($prospect, $signals, ['business_name']);

        $this->assertNull($match);
    }
}
