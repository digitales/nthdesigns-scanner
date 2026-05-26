<?php

namespace Tests\Feature;

use App\Models\OutreachSelection;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutreachSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_add_prospect_to_selection(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
        ]);

        $this->actingAs($user)
            ->post('/outreach/selections', ['prospect_ids' => [$prospect->id]])
            ->assertRedirect();

        $this->assertDatabaseHas('outreach_selections', [
            'user_id'     => $user->id,
            'prospect_id' => $prospect->id,
        ]);
    }

    public function test_user_cannot_add_another_users_prospect(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $owner->id])->id,
        ]);

        $this->actingAs($other)
            ->post('/outreach/selections', ['prospect_ids' => [$prospect->id]])
            ->assertForbidden();
    }

    public function test_user_can_remove_selection(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
        ]);
        OutreachSelection::create(['user_id' => $user->id, 'prospect_id' => $prospect->id]);

        $this->actingAs($user)
            ->delete("/outreach/selections/{$prospect->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('outreach_selections', [
            'user_id'     => $user->id,
            'prospect_id' => $prospect->id,
        ]);
    }
}
