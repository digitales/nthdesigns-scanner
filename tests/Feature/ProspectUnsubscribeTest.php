<?php

namespace Tests\Feature;

use App\Enums\IgnoredProspectReason;
use App\Enums\SuppressionSource;
use App\Models\IgnoredProspect;
use App\Models\OutreachSelection;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\SuppressedEmail;
use App\Models\User;
use App\Services\ProspectUnsubscribeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProspectUnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_unsubscribe_prospect_email(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'place_id' => 'places/foo',
            'email' => 'owner@example.com',
        ]);

        OutreachSelection::create([
            'user_id' => $user->id,
            'prospect_id' => $prospect->id,
        ]);

        $this->actingAs($user)
            ->post("/prospects/{$prospect->id}/unsubscribe")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('suppressed_emails', [
            'user_id' => $user->id,
            'email' => 'owner@example.com',
            'source' => SuppressionSource::Operator->value,
        ]);

        $this->assertDatabaseHas('ignored_prospects', [
            'user_id' => $user->id,
            'place_id' => 'places/foo',
            'reason' => IgnoredProspectReason::Unsubscribed->value,
        ]);

        $this->assertDatabaseMissing('outreach_selections', [
            'user_id' => $user->id,
            'prospect_id' => $prospect->id,
        ]);
    }

    public function test_unsubscribe_requires_email_on_prospect(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'email' => null,
        ]);

        $this->actingAs($user)
            ->post("/prospects/{$prospect->id}/unsubscribe")
            ->assertRedirect()
            ->assertSessionHasErrors('email');
    }

    public function test_undo_unsubscribed_ignore_lifts_email_suppression(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'place_id' => 'places/foo',
            'email' => 'owner@example.com',
        ]);

        app(ProspectUnsubscribeService::class)->unsubscribe(
            $user,
            $prospect,
            SuppressionSource::Operator,
        );

        $this->actingAs($user)
            ->delete("/prospects/{$prospect->id}/ignore")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('ignored_prospects', [
            'user_id' => $user->id,
            'place_id' => 'places/foo',
        ]);

        $this->assertDatabaseMissing('suppressed_emails', [
            'user_id' => $user->id,
            'email' => 'owner@example.com',
        ]);
    }

    public function test_show_includes_email_and_suppression_state(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'email' => 'owner@example.com',
        ]);

        SuppressedEmail::create([
            'user_id' => $user->id,
            'email' => 'owner@example.com',
            'source' => SuppressionSource::Operator,
            'prospect_id' => $prospect->id,
        ]);

        $this->actingAs($user)
            ->get("/prospects/{$prospect->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('prospect.email', 'owner@example.com')
                ->where('prospect.email_suppressed', true));
    }

    public function test_operator_can_update_prospect_email(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
            'audit_status' => 'complete',
        ]);

        $this->actingAs($user)
            ->patch("/prospects/{$prospect->id}", [
                'email' => 'Contact@Example.COM',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('prospects', [
            'id' => $prospect->id,
            'email' => 'contact@example.com',
        ]);
    }
}
