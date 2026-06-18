<?php

namespace Tests\Unit;

use App\Enums\ProspectValidatorStatus;
use App\Models\Prospect;
use App\Models\ProspectValidationSignal;
use App\Models\User;
use App\Services\ProspectValidationRulesService;
use App\Services\ProspectValidatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ProspectValidatorServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProspectValidatorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('prospect_validator.franchise_signals', ['portman']);
        Config::set('prospect_validator.weakness_threshold_high', 60);
        Config::set('prospect_validator.weakness_threshold_strong', 25);
        Config::set('prospect_validator.high_review_count', 500);

        $this->service = app(ProspectValidatorService::class);
    }

    public function test_operator_override_takes_precedence(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'business_name' => 'Portman Dental',
            'qualification_status' => 'qualified',
            'combined_score' => 80,
            'validator_override_status' => ProspectValidatorStatus::HighChance->value,
            'validator_override_note' => 'Confirmed independent franchisee',
            'validator_override_by' => $user->id,
            'validator_override_at' => now(),
        ]);

        $this->service->validate($prospect->fresh());

        $prospect->refresh();

        $this->assertSame(ProspectValidatorStatus::HighChance, $prospect->validator_status);
        $this->assertStringContainsString('Operator override', $prospect->validator_summary);
        $this->assertSame(['operator_override'], $prospect->validator_flags);
    }

    public function test_franchise_signal_in_business_name_returns_low_chance(): void
    {
        $prospect = Prospect::factory()->create([
            'business_name' => 'Portman Dental Care Leeds',
            'qualification_status' => 'qualified',
            'combined_score' => 80,
        ]);

        $this->service->validate($prospect);

        $prospect->refresh();

        $this->assertSame(ProspectValidatorStatus::LowChance, $prospect->validator_status);
        $this->assertContains('franchise_signal:portman:business_name', $prospect->validator_flags);
    }

    public function test_operator_signal_matches_and_records_structured_flag(): void
    {
        $user = User::factory()->create();

        ProspectValidationSignal::query()->create([
            'pattern' => 'smileworks',
            'label' => 'Smileworks',
            'active' => true,
            'created_by' => $user->id,
        ]);

        $prospect = Prospect::factory()->create([
            'business_name' => 'Smileworks City Centre',
            'qualification_status' => 'qualified',
            'combined_score' => 80,
        ]);

        app(ProspectValidationRulesService::class)->clearCache();
        $this->service->validate($prospect);

        $prospect->refresh();

        $this->assertSame(ProspectValidatorStatus::LowChance, $prospect->validator_status);
        $this->assertContains('franchise_signal:smileworks:business_name', $prospect->validator_flags);
    }

    public function test_qualified_prospect_with_high_weakness_returns_high_chance(): void
    {
        $prospect = Prospect::factory()->create([
            'business_name' => 'Independent Dental',
            'qualification_status' => 'qualified',
            'combined_score' => 75,
            'email' => 'hello@example.com',
        ]);

        $this->service->validate($prospect);

        $prospect->refresh();

        $this->assertSame(ProspectValidatorStatus::HighChance, $prospect->validator_status);
    }
}
