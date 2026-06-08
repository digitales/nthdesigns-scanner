<?php

namespace Tests\Feature;

use App\Models\NicheNote;
use App\Models\NicheTagAssignment;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NicheAnnotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_and_market_notes_are_isolated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/niche-notes', [
            'niche_label' => 'Dental Clinic',
            'body' => 'Priority vertical',
        ]);

        $this->actingAs($user)->post('/niche-notes', [
            'niche_label' => 'Dental Clinic',
            'city' => 'Leeds',
            'body' => 'Strong market',
        ]);

        $this->assertDatabaseHas('niche_notes', [
            'user_id' => $user->id,
            'niche_label' => 'Dental Clinic',
            'city' => null,
            'body' => 'Priority vertical',
        ]);

        $this->assertDatabaseHas('niche_notes', [
            'user_id' => $user->id,
            'niche_label' => 'Dental Clinic',
            'city' => 'Leeds',
            'body' => 'Strong market',
        ]);

        $response = $this->actingAs($user)->getJson('/niches/annotations?niche_label=Dental+Clinic&city=Leeds');
        $response->assertOk();
        $response->assertJsonPath('global.notes.0.body', 'Priority vertical');
        $response->assertJsonPath('market.notes.0.body', 'Strong market');
    }

    public function test_niche_tags_dedupe_case_insensitively(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/niche-tags', [
            'niche_label' => 'Dental Clinic',
            'action' => 'attach',
            'tag_name' => 'Priority',
        ]);

        $this->actingAs($user)->post('/niche-tags', [
            'niche_label' => 'Dental Clinic',
            'city' => 'Leeds',
            'action' => 'attach',
            'tag_name' => 'priority',
        ]);

        $this->assertSame(1, Tag::where('user_id', $user->id)->count());
        $this->assertSame(2, NicheTagAssignment::where('user_id', $user->id)->count());
    }
}
