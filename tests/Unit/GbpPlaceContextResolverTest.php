<?php

namespace Tests\Unit;

use App\Services\GbpPlaceContextResolver;
use Tests\TestCase;

class GbpPlaceContextResolverTest extends TestCase
{
    public function test_maps_configured_primary_type_to_niche_query(): void
    {
        $resolved = (new GbpPlaceContextResolver())->resolve([
            'primaryType' => 'dentist',
            'addressComponents' => [
                [
                    'longText'  => 'Wimbledon',
                    'shortText' => 'Wimbledon',
                    'types'     => ['locality', 'political'],
                ],
                [
                    'longText'  => 'United Kingdom',
                    'shortText' => 'GB',
                    'types'     => ['country', 'political'],
                ],
            ],
        ]);

        $this->assertSame('dentist', $resolved['niche']);
        $this->assertSame('Wimbledon', $resolved['city']);
        $this->assertSame('GB', $resolved['country']);
    }

    public function test_humanizes_unknown_primary_type(): void
    {
        $resolved = (new GbpPlaceContextResolver())->resolve([
            'primaryType' => 'fabric_store',
        ], 'GB');

        $this->assertSame('fabric store', $resolved['niche']);
        $this->assertNull($resolved['city']);
        $this->assertSame('GB', $resolved['country']);
    }

    public function test_prefers_locality_over_postal_town(): void
    {
        $resolved = (new GbpPlaceContextResolver())->resolve([
            'addressComponents' => [
                [
                    'longText' => 'SW19',
                    'types'    => ['postal_town'],
                ],
                [
                    'longText' => 'Wimbledon',
                    'types'    => ['locality'],
                ],
            ],
        ]);

        $this->assertSame('Wimbledon', $resolved['city']);
    }
}
