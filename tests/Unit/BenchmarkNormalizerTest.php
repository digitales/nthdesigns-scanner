<?php

namespace Tests\Unit;

use App\Services\BenchmarkNormalizer;
use PHPUnit\Framework\TestCase;

class BenchmarkNormalizerTest extends TestCase
{
    public function test_from_place_maps_fields(): void
    {
        $place = [
            'id' => 'places/ChIJ123',
            'displayName' => ['text' => 'Top Dental'],
            'rating' => 4.8,
            'userRatingCount' => 312,
            'photos' => [[], [], []],
            'editorialSummary' => ['text' => 'Leading practice'],
            'regularOpeningHours' => ['periods' => [['open' => ['day' => 1]]]],
        ];

        $snapshot = app(BenchmarkNormalizer::class)->fromPlace($place);

        $this->assertSame('places/ChIJ123', $snapshot['place_id']);
        $this->assertSame('Top Dental', $snapshot['name']);
        $this->assertSame(312, $snapshot['review_count']);
        $this->assertSame(3, $snapshot['photo_count']);
        $this->assertSame(4.8, $snapshot['rating']);
        $this->assertTrue($snapshot['has_description']);
        $this->assertTrue($snapshot['hours_complete']);
    }
}
