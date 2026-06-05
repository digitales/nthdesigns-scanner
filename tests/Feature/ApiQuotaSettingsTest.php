<?php

namespace Tests\Feature;

use App\Models\ApiQuotaSetting;
use App\Models\ApiUsageCounter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApiQuotaSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'scanner.api_quota.limits.google_places.text_search.daily' => 500,
            'scanner.api_quota.limits.google_places.text_search.monthly' => 10000,
            'scanner.api_quota.limits.google_places.place_details.daily' => 200,
            'scanner.api_quota.limits.google_places.place_details.monthly' => 2000,
            'scanner.api_quota.limits.brave.web_search.daily' => 100,
            'scanner.api_quota.limits.brave.web_search.monthly' => 3000,
        ]);
    }

    public function test_settings_page_includes_api_usage_snapshot(): void
    {
        $user = User::factory()->create();

        ApiUsageCounter::query()->create([
            'provider' => 'google_places',
            'operation' => 'text_search',
            'period_type' => 'daily',
            'period_key' => now('Europe/London')->toDateString(),
            'count' => 3,
        ]);

        $this->actingAs($user)
            ->get('/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Index')
                ->has('apiUsage.operations', 3)
                ->where('apiUsage.operations.0.daily.count', 3));
    }

    public function test_user_can_lower_quota_limits(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/settings/api-quotas', [
                'google_places_text_search_daily' => 100,
                'google_places_text_search_monthly' => null,
                'google_places_place_details_daily' => null,
                'google_places_place_details_monthly' => null,
                'brave_web_search_daily' => null,
                'brave_web_search_monthly' => null,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'API quota limits saved.');

        $this->assertDatabaseHas('api_quota_settings', [
            'id' => 1,
            'google_places_text_search_daily' => 100,
        ]);
    }

    public function test_quota_override_cannot_exceed_env_ceiling(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/settings/api-quotas', [
                'google_places_text_search_daily' => 9999,
            ])
            ->assertSessionHasErrors('google_places_text_search_daily');

        $this->assertNull(ApiQuotaSetting::current()->google_places_text_search_daily);
    }

    public function test_api_quota_settings_require_auth(): void
    {
        $this->patch('/settings/api-quotas')->assertRedirect('/login');
    }
}
