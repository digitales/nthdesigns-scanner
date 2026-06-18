<?php

namespace Tests\Feature;

use App\Jobs\RevalidateProspectsForSignalJob;
use App\Models\ProspectValidationSignal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ProspectValidationSignalTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_create_validation_signal(): void
    {
        Bus::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/settings/validation-rules', [
                'pattern' => 'smileworks',
                'label' => 'Smileworks Dental Group',
                'notes' => 'Seen in Manchester search',
            ])
            ->assertRedirect(route('settings.validation-rules.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('prospect_validation_signals', [
            'pattern' => 'smileworks',
            'label' => 'Smileworks Dental Group',
            'created_by' => $user->id,
            'active' => true,
        ]);

        Bus::assertDispatched(RevalidateProspectsForSignalJob::class);
    }

    public function test_deactivating_signal_dispatches_revalidation_job(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $signal = ProspectValidationSignal::query()->create([
            'pattern' => 'smileworks',
            'label' => 'Smileworks',
            'active' => true,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete("/settings/validation-rules/{$signal->id}")
            ->assertRedirect(route('settings.validation-rules.index'));

        $this->assertFalse($signal->fresh()->active);
        Bus::assertDispatched(RevalidateProspectsForSignalJob::class);
    }

    public function test_validation_rules_page_lists_operator_signals(): void
    {
        $user = User::factory()->create();
        ProspectValidationSignal::query()->create([
            'pattern' => 'smileworks',
            'label' => 'Smileworks',
            'active' => true,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/settings/validation-rules')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/ValidationRules')
                ->has('operatorSignals', 1)
                ->where('operatorSignals.0.pattern', 'smileworks'));
    }
}
