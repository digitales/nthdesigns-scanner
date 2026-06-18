<?php

namespace Tests\Unit;

use App\Models\ProspectValidationSignal;
use App\Models\User;
use App\Services\ProspectValidationRulesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ProspectValidationRulesServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_merges_config_and_active_operator_signals(): void
    {
        Config::set('prospect_validator.franchise_signals', ['portman', 'mydentist']);

        $user = User::factory()->create();

        ProspectValidationSignal::query()->create([
            'pattern' => 'smileworks',
            'label' => 'Smileworks',
            'active' => true,
            'created_by' => $user->id,
        ]);

        $service = app(ProspectValidationRulesService::class);
        $signals = $service->activeFranchiseSignals();

        $this->assertCount(3, $signals);
        $this->assertSame('smileworks', $signals->firstWhere('source', 'operator')['pattern']);
    }

    public function test_excludes_deactivated_operator_signals(): void
    {
        $user = User::factory()->create();

        ProspectValidationSignal::query()->create([
            'pattern' => 'smileworks',
            'label' => 'Smileworks',
            'active' => false,
            'created_by' => $user->id,
        ]);

        $service = app(ProspectValidationRulesService::class);

        $this->assertFalse(
            $service->activeFranchiseSignals()->contains(fn ($signal) => $signal['pattern'] === 'smileworks'),
        );
    }

    public function test_operator_signal_wins_on_duplicate_pattern(): void
    {
        Config::set('prospect_validator.franchise_signals', ['portman']);

        $user = User::factory()->create();

        ProspectValidationSignal::query()->create([
            'pattern' => 'portman',
            'label' => 'Portman override label',
            'active' => true,
            'created_by' => $user->id,
        ]);

        $service = app(ProspectValidationRulesService::class);
        $portmanSignals = $service->activeFranchiseSignals()->where('pattern', 'portman');

        $this->assertCount(1, $portmanSignals);
        $this->assertSame('operator', $portmanSignals->first()['source']);
    }
}
