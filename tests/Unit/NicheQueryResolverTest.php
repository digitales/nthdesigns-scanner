<?php

namespace Tests\Unit;

use App\Support\NicheQueryResolver;
use Tests\TestCase;

class NicheQueryResolverTest extends TestCase
{
    public function test_resolves_query_from_config_label(): void
    {
        config([
            'niches.niches' => [
                ['label' => 'Dental Clinic', 'query' => 'dental clinic'],
            ],
        ]);

        $this->assertSame('dental clinic', NicheQueryResolver::forLabel('Dental Clinic'));
    }

    public function test_falls_back_to_lowercased_label(): void
    {
        config(['niches.niches' => []]);

        $this->assertSame('custom niche', NicheQueryResolver::forLabel('Custom Niche'));
    }
}
