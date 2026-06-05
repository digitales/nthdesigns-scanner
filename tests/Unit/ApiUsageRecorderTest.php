<?php

namespace Tests\Unit;

use App\Models\ApiUsageCounter;
use App\Services\ApiUsage\ApiUsageRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiUsageRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_increment_updates_daily_and_monthly_counters(): void
    {
        $at = now('Europe/London')->startOfDay();

        app(ApiUsageRecorder::class)->increment('google_places', 'text_search', $at);

        $this->assertDatabaseHas('api_usage_counters', [
            'provider' => 'google_places',
            'operation' => 'text_search',
            'period_type' => 'daily',
            'period_key' => $at->toDateString(),
            'count' => 1,
        ]);

        $this->assertDatabaseHas('api_usage_counters', [
            'provider' => 'google_places',
            'operation' => 'text_search',
            'period_type' => 'monthly',
            'period_key' => $at->format('Y-m'),
            'count' => 1,
        ]);
    }

    public function test_increment_is_atomic_for_existing_row(): void
    {
        $at = now('Europe/London');

        ApiUsageCounter::query()->create([
            'provider' => 'brave',
            'operation' => 'web_search',
            'period_type' => 'daily',
            'period_key' => $at->toDateString(),
            'count' => 2,
        ]);

        app(ApiUsageRecorder::class)->increment('brave', 'web_search', $at);

        $this->assertSame(3, app(ApiUsageRecorder::class)->countFor('brave', 'web_search', 'daily', $at));
    }
}
