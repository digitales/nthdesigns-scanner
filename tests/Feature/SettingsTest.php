<?php

namespace Tests\Feature;

use App\Models\NicheScan;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_requires_auth(): void
    {
        $this->get('/settings')->assertRedirect('/login');
    }

    public function test_user_can_update_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/settings', [
                'default_country' => 'IE',
                'agency_name'     => 'nthdesigns',
                'booking_url'     => 'https://example.com/book',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_settings', [
            'user_id'         => $user->id,
            'default_country' => 'IE',
            'agency_name'     => 'nthdesigns',
            'booking_url'     => 'https://example.com/book',
        ]);
    }

    public function test_search_form_uses_default_country_from_settings(): void
    {
        $user = User::factory()->create();
        UserSetting::create([
            'user_id'         => $user->id,
            'default_country' => 'IE',
        ]);

        $this->actingAs($user)
            ->get('/search')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Search/Index')
                ->where('defaults.country', 'IE'));
    }

    public function test_settings_page_includes_niche_maintenance_stats(): void
    {
        $user = User::factory()->create();

        NicheScan::query()->create([
            'niche' => 'Dental Practice',
            'niche_query' => 'dental practice',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => '2026-05-27',
            'result_count' => 10,
            'sampled_count' => 5,
            'avg_gbp_score' => 50,
            'pct_no_website' => 20,
            'pct_low_reviews' => 40,
            'opportunity_score' => 45,
            'status' => 'complete',
            'ran_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Index')
                ->has('nicheMaintenance')
                ->where('nicheMaintenance.niche_count', fn ($v) => $v >= 1)
                ->where('nicheMaintenance.city_count', fn ($v) => $v >= 1)
                ->where('nicheMaintenance.last_scan_human', fn ($v) => $v !== 'Never')
            );
    }

    public function test_scan_niches_queues_command(): void
    {
        $user = User::factory()->create();

        Artisan::shouldReceive('queue')
            ->once()
            ->with('niches:scan', []);

        $this->actingAs($user)
            ->post('/settings/niches/scan')
            ->assertRedirect()
            ->assertSessionHas('success', 'Market scan queued.');
    }

    public function test_scan_niches_force_queues_with_force_flag(): void
    {
        $user = User::factory()->create();

        Artisan::shouldReceive('queue')
            ->once()
            ->with('niches:scan', ['--force' => true]);

        $this->actingAs($user)
            ->post('/settings/niches/scan', ['force' => true])
            ->assertRedirect()
            ->assertSessionHas('success', 'Market scan queued.');
    }

    public function test_bootstrap_niches_requires_refresh_confirmation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/settings/niches/bootstrap', ['confirm' => 'NOPE'])
            ->assertSessionHasErrors('confirm');

        Artisan::shouldReceive('queue')->never();
    }

    public function test_bootstrap_niches_queues_command(): void
    {
        $user = User::factory()->create();

        Artisan::shouldReceive('queue')
            ->once()
            ->with('niches:bootstrap', [
                '--no-interaction' => true,
                '--force' => true,
            ]);

        $this->actingAs($user)
            ->post('/settings/niches/bootstrap', ['confirm' => 'REFRESH'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Catalog refresh queued.');
    }

    public function test_niche_maintenance_requires_auth(): void
    {
        $this->post('/settings/niches/scan')->assertRedirect('/login');
        $this->post('/settings/niches/bootstrap')->assertRedirect('/login');
    }
}
