<?php

namespace Tests\Feature;

use App\Jobs\AuditSiteJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProspectEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_patch_prospect_fields(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id, 'scan_type' => 'gbp_only']);
        $prospect = Prospect::factory()->create([
            'search_id'       => $search->id,
            'phone'           => null,
            'website_url'     => null,
            'gbp_flags'       => ['No phone number listed', 'No website listed'],
            'raw_gbp_payload' => [],
        ]);

        $this->actingAs($user)
            ->patch("/prospects/{$prospect->id}", [
                'phone'       => '+441234567890',
                'website_url' => 'https://example.com',
            ])
            ->assertRedirect();

        $prospect->refresh();
        $this->assertSame('+441234567890', $prospect->phone);
        $this->assertSame('https://example.com', $prospect->website_url);
        $this->assertNotContains('No phone number listed', $prospect->gbp_flags);
        $this->assertNotContains('No website listed', $prospect->gbp_flags);
    }

    public function test_other_user_cannot_patch_prospect(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $owner->id])->id,
        ]);

        $this->actingAs($other)
            ->patch("/prospects/{$prospect->id}", ['phone' => '+441234'])
            ->assertForbidden();
    }

    public function test_website_change_dispatches_audit_for_combined_search(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id, 'scan_type' => 'combined']);
        $prospect = Prospect::factory()->create([
            'search_id'        => $search->id,
            'website_url'      => null,
            'audit_status'     => 'skipped',
            'raw_a11y_payload' => ['violations' => []],
            'a11y_score'       => 50,
        ]);

        $this->actingAs($user)
            ->patch("/prospects/{$prospect->id}", ['website_url' => 'https://new.example'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Details saved. Site audit queued.');

        $prospect->refresh();
        $this->assertSame('pending', $prospect->audit_status);
        $this->assertNull($prospect->raw_a11y_payload);
        $this->assertTrue($prospect->suppress_auto_report);

        Queue::assertPushed(AuditSiteJob::class);
    }

    public function test_patch_rejected_when_audit_pending(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id'    => Search::factory()->create(['user_id' => $user->id])->id,
            'audit_status' => 'pending',
        ]);

        $this->actingAs($user)
            ->patch("/prospects/{$prospect->id}", ['phone' => '+441234'])
            ->assertSessionHasErrors('website_url');
    }

    public function test_owner_can_add_note(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
        ]);

        $this->actingAs($user)
            ->post("/prospects/{$prospect->id}/notes", ['body' => 'Called — no answer'])
            ->assertRedirect();

        $this->assertDatabaseHas('prospect_notes', [
            'prospect_id' => $prospect->id,
            'user_id'     => $user->id,
            'body'        => 'Called — no answer',
        ]);
    }

    public function test_show_includes_notes_newest_first(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
        ]);

        $prospect->notes()->create(['user_id' => $user->id, 'body' => 'First', 'created_at' => now()->subHour()]);
        $prospect->notes()->create(['user_id' => $user->id, 'body' => 'Second', 'created_at' => now()]);

        $this->actingAs($user)
            ->get("/prospects/{$prospect->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('notes', 2)
                ->where('notes.0.body', 'Second')
                ->where('notes.1.body', 'First'));
    }
}
