<?php

namespace Tests\Unit;

use App\Models\Search;
use App\Services\DirectUrlSearchEnrichment;
use App\Services\GooglePlacesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectUrlSearchEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetches_benchmark_when_niche_and_city_resolved(): void
    {
        $search = Search::factory()->directUrl('https://example.com')->create([
            'country' => 'GB',
        ]);

        $this->mock(GooglePlacesService::class, function ($mock) {
            $mock->shouldReceive('getTopRankedInNiche')
                ->once()
                ->with('dentist', 'Wimbledon', 'GB', 'places/prospect')
                ->andReturn([
                    'id' => 'places/leader',
                    'displayName' => ['text' => 'Top Dentist'],
                    'userRatingCount' => 120,
                    'photos' => array_fill(0, 20, []),
                    'rating' => 4.9,
                ]);
        });

        $attributes = app(DirectUrlSearchEnrichment::class)->attributesFor($search, [
            'id' => 'places/prospect',
            'primaryType' => 'dentist',
            'addressComponents' => [
                ['longText' => 'Wimbledon', 'types' => ['locality']],
                ['shortText' => 'GB', 'types' => ['country']],
            ],
        ]);

        $this->assertSame('dentist', $attributes['niche']);
        $this->assertSame('Wimbledon', $attributes['city']);
        $this->assertSame('GB', $attributes['country']);
        $this->assertSame('places/leader', $attributes['benchmark_snapshot']['place_id']);
        $this->assertSame('Top Dentist', $attributes['benchmark_snapshot']['name']);
    }

    public function test_skips_benchmark_when_city_missing(): void
    {
        $search = Search::factory()->directUrl('https://example.com')->create();

        $this->mock(GooglePlacesService::class, function ($mock) {
            $mock->shouldNotReceive('getTopRankedInNiche');
        });

        $attributes = app(DirectUrlSearchEnrichment::class)->attributesFor($search, [
            'id' => 'places/prospect',
            'primaryType' => 'dentist',
        ]);

        $this->assertSame('dentist', $attributes['niche']);
        $this->assertArrayNotHasKey('city', $attributes);
        $this->assertArrayNotHasKey('benchmark_snapshot', $attributes);
    }
}
