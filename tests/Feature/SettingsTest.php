<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
