<?php

namespace Tests\Feature;

use App\Enums\IgnoredNicheReason;
use App\Enums\NicheScanStatus;
use App\Models\IgnoredNiche;
use App\Models\NicheScan;
use App\Models\User;
use App\Services\NicheExclusionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NicheExclusionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_ignores_niche_when_max_results_below_threshold(): void
    {
        config([
            'niches.min_result_count' => 3,
            'niches.niches' => [
                ['label' => 'Span', 'query' => 'span', 'primary_type' => 'span'],
            ],
        ]);

        foreach (['Leeds', 'Manchester'] as $city) {
            NicheScan::query()->create([
                'niche' => 'Span',
                'niche_query' => 'span',
                'city' => $city,
                'country' => 'GB',
                'scan_date' => '2026-05-27',
                'result_count' => 1,
                'sampled_count' => 1,
                'status' => NicheScanStatus::Complete,
                'ran_at' => now(),
            ]);
        }

        app(NicheExclusionService::class)->refreshForNiche('Span');

        $this->assertDatabaseHas('ignored_niches', [
            'niche' => 'Span',
            'reason' => IgnoredNicheReason::LowResults->value,
        ]);
    }

    public function test_manual_ignore_is_not_removed_by_auto_refresh(): void
    {
        config([
            'niches.min_result_count' => 3,
            'niches.niches' => [
                ['label' => 'Span', 'query' => 'span', 'primary_type' => 'span'],
            ],
        ]);

        IgnoredNiche::query()->create([
            'niche' => 'Span',
            'reason' => IgnoredNicheReason::Manual->value,
        ]);

        NicheScan::query()->create([
            'niche' => 'Span',
            'niche_query' => 'span',
            'city' => 'Leeds',
            'country' => 'GB',
            'scan_date' => '2026-05-27',
            'result_count' => 40,
            'sampled_count' => 5,
            'status' => NicheScanStatus::Complete,
            'ran_at' => now(),
        ]);

        app(NicheExclusionService::class)->refreshForNiche('Span');

        $this->assertDatabaseHas('ignored_niches', [
            'niche' => 'Span',
            'reason' => IgnoredNicheReason::Manual->value,
        ]);
    }

    public function test_include_override_prevents_re_auto_ignore(): void
    {
        config([
            'niches.min_result_count' => 3,
            'niches.niches' => [
                ['label' => 'Span', 'query' => 'span', 'primary_type' => 'span'],
            ],
        ]);

        IgnoredNiche::query()->create([
            'niche' => 'Span',
            'reason' => IgnoredNicheReason::LowResults->value,
        ]);

        app(NicheExclusionService::class)->includeInScans('Span');

        $this->assertDatabaseMissing('ignored_niches', ['niche' => 'Span']);
        $this->assertDatabaseHas('niche_inclusion_overrides', ['niche' => 'Span']);

        app(NicheExclusionService::class)->refreshForNiche('Span');

        $this->assertDatabaseMissing('ignored_niches', ['niche' => 'Span']);
    }

    public function test_user_can_ignore_and_include_niche_via_http(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/niches/ignore', ['niche' => 'Dental Clinic'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('ignored_niches', [
            'niche' => 'Dental Clinic',
            'reason' => IgnoredNicheReason::Manual->value,
        ]);

        $this->actingAs($user)
            ->post('/niches/ignore/remove', ['niche' => 'Dental Clinic'])
            ->assertRedirect();

        $this->assertDatabaseMissing('ignored_niches', ['niche' => 'Dental Clinic']);
    }
}
