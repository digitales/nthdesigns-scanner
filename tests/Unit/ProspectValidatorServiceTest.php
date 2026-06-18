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

    public function test_should_skip_qualification_for_franchise_signal_in_business_name(): void
    {
        $prospect = Prospect::factory()->create([
            'business_name' => 'Portman Dental Care Leeds',
            'qualification_status' => null,
            'combined_score' => 75,
        ]);

        $this->assertTrue($this->service->shouldSkipQualification($prospect));
    }

    public function test_should_skip_qualification_when_already_digitally_strong(): void
    {
        $prospect = Prospect::factory()->create([
            'business_name' => 'Independent Dental',
            'qualification_status' => null,
            'combined_score' => 10,
        ]);

        $this->assertTrue($this->service->shouldSkipQualification($prospect));
    }

    public function test_should_skip_qualification_when_qualification_status_is_skip(): void
    {
        $prospect = Prospect::factory()->create([
            'business_name' => 'Portman Dental Group',
            'qualification_status' => 'skip',
            'qualification_flags' => ['corporate_chain'],
            'combined_score' => 75,
        ]);

        $this->assertTrue($this->service->shouldSkipQualification($prospect));
    }

    public function test_should_not_skip_qualification_for_insufficient_qualification_data(): void
    {
        $prospect = Prospect::factory()->create([
            'business_name' => 'Independent Dental',
            'qualification_status' => null,
            'combined_score' => 40,
        ]);

        $this->assertFalse($this->service->shouldSkipQualification($prospect));
    }

    public function test_wrong_niche_skip_uses_qualification_summary_not_corporate_message(): void
    {
        $user = User::factory()->create();
        $search = \App\Models\Search::factory()->create([
            'user_id' => $user->id,
            'niche' => 'dental practice',
        ]);

        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'qualification_status' => 'skip',
            'qualification_summary' => 'Website is a business consultancy, not a dental practice.',
            'qualification_flags' => [
                'Not a dental practice',
                'Business consultancy website',
            ],
        ]);

        $this->service->validate($prospect);

        $prospect->refresh();

        $this->assertSame(ProspectValidatorStatus::LowChance, $prospect->validator_status);
        $this->assertStringContainsString('consultancy', $prospect->validator_summary);
        $this->assertStringNotContainsString('Corporate group or franchise', $prospect->validator_summary);
        $this->assertContains('wrong_niche_match', $prospect->validator_flags);
        $this->assertNotContains('corporate_or_franchise_confirmed', $prospect->validator_flags);
    }

    public function test_corporate_chain_skip_keeps_corporate_validator_message(): void
    {
        $prospect = Prospect::factory()->create([
            'qualification_status' => 'skip',
            'qualification_summary' => 'Part of a national dental chain.',
            'qualification_flags' => ['corporate_chain', 'Parent company in footer'],
        ]);

        $this->service->validate($prospect);

        $prospect->refresh();

        $this->assertSame(ProspectValidatorStatus::LowChance, $prospect->validator_status);
        $this->assertStringContainsString('Corporate group or franchise', $prospect->validator_summary);
        $this->assertContains('corporate_or_franchise_confirmed', $prospect->validator_flags);
    }

    public function test_qualified_prospect_does_not_false_positive_on_negated_qualification_flags(): void
    {
        $prospect = Prospect::factory()->create([
            'business_name' => 'Sunna Dental',
            'website_url' => 'https://www.sunnadental.co.uk/',
            'qualification_status' => 'qualified',
            'qualification_summary' => 'Independent private dental practice on Stratford Road, Birmingham.',
            'qualification_flags' => [
                'Single location practice on Stratford Road, Birmingham',
                'No parent company or group branding in footer',
                'No corporate booking platform URLs detected',
                'WordPress-based website suggesting independent operation',
            ],
            'combined_score' => 75,
            'email' => 'reception@sunnadental.co.uk',
        ]);

        $this->service->validate($prospect);

        $prospect->refresh();

        $this->assertSame(ProspectValidatorStatus::HighChance, $prospect->validator_status);
        $this->assertStringNotContainsString('Franchise or corporate group', $prospect->validator_summary);
    }
}
