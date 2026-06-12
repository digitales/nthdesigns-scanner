<?php

namespace Tests\Unit;

use App\Models\Search;
use App\Models\User;
use App\Services\MarketCpcDefaultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketCpcDefaultServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_from_default_copies_cpc_and_keywords_to_search(): void
    {
        $user = User::factory()->create();
        $service = app(MarketCpcDefaultService::class);

        $service->upsert($user, 'Dental Practice', 'Birmingham', 'GB', [
            'cpc_benchmark' => 9.25,
            'cpc_source' => 'manual',
            'cpc_keywords' => ['dentist birmingham'],
            'cpc_geo_target' => 'geoTargetConstants/9041139',
        ]);

        $search = Search::factory()->create([
            'user_id' => $user->id,
            'niche' => 'dental practice',
            'city' => 'Birmingham',
            'country' => 'GB',
        ]);

        $service->applyFromDefault($search, $user);

        $search->refresh();

        $this->assertSame('9.25', $search->cpc_benchmark);
        $this->assertSame('manual', $search->cpc_source);
        $this->assertSame(['dentist birmingham'], $search->cpc_keywords);
    }
}
