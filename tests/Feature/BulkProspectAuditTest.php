<?php

namespace Tests\Feature;

use App\Enums\AuditStatus;
use App\Enums\ScanType;
use App\Enums\SearchStatus;
use App\Jobs\AuditSiteJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BulkProspectAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_bulk_reaudit_failed_rows(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'status' => SearchStatus::Complete,
            'scan_type' => ScanType::Combined,
        ]);
        $failed = Prospect::factory()->create([
            'search_id' => $search->id,
            'website_url' => 'https://failed.example',
            'audit_status' => AuditStatus::Failed,
            'gbp_score' => 70,
            'raw_a11y_payload' => ['error' => 'timeout'],
        ]);
        $complete = Prospect::factory()->create([
            'search_id' => $search->id,
            'website_url' => 'https://ok.example',
            'audit_status' => AuditStatus::Complete,
        ]);

        $this->actingAs($user)
            ->post("/searches/{$search->id}/bulk-audit", [
                'prospect_ids' => [$failed->id, $complete->id],
                'mode' => 'failed',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $failed->refresh();
        $this->assertSame(AuditStatus::Pending, $failed->audit_status);
        $this->assertNull($failed->raw_a11y_payload);
        $this->assertSame(70, $failed->gbp_score);

        $complete->refresh();
        $this->assertSame(AuditStatus::Complete, $complete->audit_status);

        Queue::assertPushed(AuditSiteJob::class, 1);
    }

    public function test_force_mode_reaudits_complete_rows(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'status' => SearchStatus::Complete,
            'scan_type' => ScanType::Combined,
        ]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'website_url' => 'https://ok.example',
            'audit_status' => AuditStatus::Complete,
            'a11y_score' => 80,
        ]);

        $this->actingAs($user)
            ->post("/searches/{$search->id}/bulk-audit", [
                'prospect_ids' => [$prospect->id],
                'mode' => 'force',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Queued 1 site audit.');

        $prospect->refresh();
        $this->assertSame(AuditStatus::Pending, $prospect->audit_status);
        $this->assertSame(0, $prospect->a11y_score);

        Queue::assertPushed(AuditSiteJob::class);
    }

    public function test_force_mode_restarts_pending_rows(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'status' => SearchStatus::Auditing,
            'scan_type' => ScanType::Combined,
        ]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'website_url' => 'https://stuck.example',
            'audit_status' => AuditStatus::Pending,
        ]);

        $this->actingAs($user)
            ->post("/searches/{$search->id}/bulk-audit", [
                'prospect_ids' => [$prospect->id],
                'mode' => 'force',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Queued 1 site audit.');

        Queue::assertPushed(AuditSiteJob::class);
    }

    public function test_failed_mode_skips_pending_rows(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'status' => SearchStatus::Auditing,
            'scan_type' => ScanType::Combined,
        ]);
        $pending = Prospect::factory()->create([
            'search_id' => $search->id,
            'website_url' => 'https://pending.example',
            'audit_status' => AuditStatus::Pending,
        ]);

        $this->actingAs($user)
            ->post("/searches/{$search->id}/bulk-audit", [
                'prospect_ids' => [$pending->id],
                'mode' => 'failed',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'No site audits queued. Skipped 1 (1 pending).');

        Queue::assertNothingPushed();
    }

    public function test_skips_rows_without_url(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'status' => SearchStatus::Complete,
            'scan_type' => ScanType::Combined,
        ]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'website_url' => null,
            'audit_status' => AuditStatus::Failed,
        ]);

        $this->actingAs($user)
            ->post("/searches/{$search->id}/bulk-audit", [
                'prospect_ids' => [$prospect->id],
                'mode' => 'force',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'No site audits queued. Skipped 1 (1 no website).');

        Queue::assertNothingPushed();
    }

    public function test_rejects_prospect_ids_from_another_search(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'status' => SearchStatus::Complete,
        ]);
        $otherProspect = Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
        ]);

        $this->actingAs($user)
            ->post("/searches/{$search->id}/bulk-audit", [
                'prospect_ids' => [$otherProspect->id],
                'mode' => 'force',
            ])
            ->assertSessionHasErrors('prospect_ids');
    }

    public function test_rejects_during_discovering_phase(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'status' => SearchStatus::Discovering,
        ]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'website_url' => 'https://example.com',
            'audit_status' => AuditStatus::Failed,
        ]);

        $this->actingAs($user)
            ->post("/searches/{$search->id}/bulk-audit", [
                'prospect_ids' => [$prospect->id],
                'mode' => 'failed',
            ])
            ->assertSessionHasErrors('prospect_ids');
    }

    public function test_other_user_forbidden(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $owner->id,
            'status' => SearchStatus::Complete,
        ]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'website_url' => 'https://example.com',
            'audit_status' => AuditStatus::Failed,
        ]);

        $this->actingAs($other)
            ->post("/searches/{$search->id}/bulk-audit", [
                'prospect_ids' => [$prospect->id],
                'mode' => 'failed',
            ])
            ->assertForbidden();
    }

    public function test_staggered_dispatch_queues_multiple_jobs(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'status' => SearchStatus::Complete,
            'scan_type' => ScanType::Combined,
        ]);
        $prospects = collect(range(1, 3))->map(fn () => Prospect::factory()->create([
            'search_id' => $search->id,
            'website_url' => 'https://example-'.fake()->unique()->numerify('###').'.com',
            'audit_status' => AuditStatus::Failed,
        ]));

        $this->actingAs($user)
            ->post("/searches/{$search->id}/bulk-audit", [
                'prospect_ids' => $prospects->pluck('id')->all(),
                'mode' => 'failed',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Queued 3 site audits.');

        Queue::assertPushed(AuditSiteJob::class, 3);
    }
}
