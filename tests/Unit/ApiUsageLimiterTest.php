<?php

namespace Tests\Unit;

use App\Exceptions\ApiQuotaExceededException;
use App\Models\ApiQuotaSetting;
use App\Models\ApiUsageCounter;
use App\Services\ApiUsage\ApiQuotaSettingsService;
use App\Services\ApiUsage\ApiUsageLimiter;
use App\Services\ApiUsage\ApiUsageRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiUsageLimiterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'scanner.api_quota.enforcement' => true,
            'scanner.api_quota.warning_percent' => 80,
            'scanner.api_quota.limits.google_places.text_search.daily' => 10,
            'scanner.api_quota.limits.google_places.text_search.monthly' => 100,
        ]);
    }

    public function test_effective_limit_uses_env_when_no_override(): void
    {
        $service = app(ApiQuotaSettingsService::class);

        $this->assertSame(10, $service->effectiveLimit('google_places', 'text_search', 'daily'));
    }

    public function test_effective_limit_uses_lower_settings_override(): void
    {
        ApiQuotaSetting::current()->update([
            'google_places_text_search_daily' => 5,
        ]);

        $service = app(ApiQuotaSettingsService::class);

        $this->assertSame(5, $service->effectiveLimit('google_places', 'text_search', 'daily'));
    }

    public function test_snapshot_reports_warning_at_eighty_percent(): void
    {
        ApiUsageCounter::query()->create([
            'provider' => 'google_places',
            'operation' => 'text_search',
            'period_type' => 'daily',
            'period_key' => now('Europe/London')->toDateString(),
            'count' => 8,
        ]);

        $snapshot = app(ApiUsageLimiter::class)->snapshot('google_places', 'text_search');

        $this->assertSame('warning', $snapshot['status']);
        $this->assertSame(80.0, $snapshot['daily']['pct']);
    }

    public function test_assert_within_quota_throws_when_daily_limit_reached(): void
    {
        ApiUsageCounter::query()->create([
            'provider' => 'google_places',
            'operation' => 'text_search',
            'period_type' => 'daily',
            'period_key' => now('Europe/London')->toDateString(),
            'count' => 10,
        ]);

        $this->expectException(ApiQuotaExceededException::class);

        app(ApiUsageLimiter::class)->assertWithinQuota('google_places', 'text_search');
    }

    public function test_assert_within_quota_skipped_when_enforcement_disabled(): void
    {
        config(['scanner.api_quota.enforcement' => false]);

        ApiUsageCounter::query()->create([
            'provider' => 'google_places',
            'operation' => 'text_search',
            'period_type' => 'daily',
            'period_key' => now('Europe/London')->toDateString(),
            'count' => 999,
        ]);

        app(ApiUsageLimiter::class)->assertWithinQuota('google_places', 'text_search');

        $this->assertTrue(true);
    }
}
