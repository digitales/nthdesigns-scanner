<?php

namespace Tests\Unit\GoogleAds;

use App\Services\GoogleAds\CpcKeywordSeeder;
use Tests\TestCase;

class CpcKeywordSeederTest extends TestCase
{
    public function test_builds_local_commercial_seeds(): void
    {
        config(['google_ads.max_seed_keywords' => 3]);

        $seeds = app(CpcKeywordSeeder::class)->seeds('dental practice', 'Birmingham', 'GB');

        $this->assertSame([
            'dental practice Birmingham',
            'dental practice in Birmingham',
            'local dental practice Birmingham',
        ], $seeds);
    }

    public function test_returns_empty_when_niche_or_city_missing(): void
    {
        $seeder = app(CpcKeywordSeeder::class);

        $this->assertSame([], $seeder->seeds('', 'Birmingham'));
        $this->assertSame([], $seeder->seeds('dentist', ''));
    }
}
