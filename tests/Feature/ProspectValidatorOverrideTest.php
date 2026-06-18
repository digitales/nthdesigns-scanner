<?php

namespace Tests\Feature;

use App\Enums\ProspectValidatorStatus;
use App\Jobs\ValidateProspectJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ProspectValidatorOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_set_validator_override(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'validator_status' => ProspectValidatorStatus::LowChance->value,
        ]);

        $this->actingAs($user)
            ->post("/prospects/{$prospect->id}/validator-override", [
                'status' => ProspectValidatorStatus::HighChance->value,
                'note' => 'Confirmed independent owner',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $prospect->refresh();

        $this->assertSame(ProspectValidatorStatus::HighChance, $prospect->validator_override_status);
        $this->assertSame('Confirmed independent owner', $prospect->validator_override_note);
        $this->assertSame($user->id, $prospect->validator_override_by);

        Bus::assertDispatched(ValidateProspectJob::class);
    }

    public function test_operator_can_clear_validator_override(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'validator_override_status' => ProspectValidatorStatus::HighChance->value,
            'validator_override_note' => 'Temporary',
            'validator_override_by' => $user->id,
            'validator_override_at' => now(),
        ]);

        $this->actingAs($user)
            ->delete("/prospects/{$prospect->id}/validator-override")
            ->assertRedirect()
            ->assertSessionHas('success');

        $prospect->refresh();

        $this->assertNull($prospect->validator_override_status);
        $this->assertNull($prospect->validator_override_note);

        Bus::assertDispatched(ValidateProspectJob::class);
    }

    public function test_revalidate_endpoint_queues_validation_job(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
        ]);

        $this->actingAs($user)
            ->postJson("/prospects/{$prospect->id}/validate")
            ->assertAccepted();

        Bus::assertDispatched(ValidateProspectJob::class);
    }
}
