<?php

namespace Tests\Feature;

use App\Jobs\AuditSiteJob;
use App\Jobs\GenerateProspectReportJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProspectReauditTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_queue_site_only_reaudit(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id, 'scan_type' => 'combined']);
        $prospect = Prospect::factory()->create([
            'search_id'            => $search->id,
            'website_url'          => 'https://goodfabrics.example',
            'audit_status'         => 'complete',
            'gbp_score'            => 72,
            'gbp_flags'            => ['Under 20 reviews'],
            'raw_gbp_payload'      => ['displayName' => ['text' => 'Good Fabrics']],
            'raw_a11y_payload'     => ['error' => 'page.goto: Timeout', 'violations' => []],
            'a11y_flags'           => ['Site audit failed to load'],
            'a11y_score'           => 50,
            'suppress_auto_report' => false,
        ]);

        $this->actingAs($user)
            ->post("/prospects/{$prospect->id}/audit")
            ->assertRedirect()
            ->assertSessionHas('success', 'Site audit queued. GBP scores unchanged.');

        $prospect->refresh();
        $this->assertSame('pending', $prospect->audit_status);
        $this->assertNull($prospect->raw_a11y_payload);
        $this->assertSame(72, $prospect->gbp_score);
        $this->assertSame(['Under 20 reviews'], $prospect->gbp_flags);
        $this->assertTrue($prospect->suppress_auto_report);

        Queue::assertPushed(AuditSiteJob::class);
        Queue::assertNotPushed(GenerateProspectReportJob::class);
    }

    public function test_reaudit_rejected_when_audit_pending(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id'    => Search::factory()->create(['user_id' => $user->id, 'scan_type' => 'combined'])->id,
            'website_url'  => 'https://example.com',
            'audit_status' => 'pending',
        ]);

        $this->actingAs($user)
            ->post("/prospects/{$prospect->id}/audit")
            ->assertSessionHasErrors('website_url');
    }

    public function test_other_user_cannot_reaudit(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $prospect = Prospect::factory()->create([
            'search_id'   => Search::factory()->create(['user_id' => $owner->id, 'scan_type' => 'combined'])->id,
            'website_url' => 'https://example.com',
        ]);

        $this->actingAs($other)
            ->post("/prospects/{$prospect->id}/audit")
            ->assertForbidden();
    }

    public function test_gbp_only_prospect_with_website_can_queue_site_audit(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id, 'scan_type' => 'gbp_only']);
        $prospect = Prospect::factory()->create([
            'search_id'        => $search->id,
            'website_url'      => 'https://sustainable-health.example',
            'audit_status'     => 'complete',
            'gbp_score'        => 68,
            'combined_score'   => 68,
            'raw_a11y_payload' => null,
        ]);

        $this->actingAs($user)
            ->post("/prospects/{$prospect->id}/audit")
            ->assertRedirect()
            ->assertSessionHas('success', 'Site audit queued. GBP scores unchanged.');

        $prospect->refresh();
        $this->assertSame('pending', $prospect->audit_status);
        $this->assertSame(68, $prospect->gbp_score);

        Queue::assertPushed(AuditSiteJob::class);
    }
}
