<?php

namespace Tests\Unit;

use App\Jobs\AuditSiteJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Services\ProspectAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProspectAuditServiceRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_repair_site_audit_allows_already_pending_prospect(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id'   => $user->id,
            'scan_type' => 'combined',
        ]);
        $prospect = Prospect::factory()->create([
            'search_id'        => $search->id,
            'website_url'      => 'https://example.com',
            'audit_status'     => 'pending',
            'raw_a11y_payload' => ['partial' => true],
        ]);

        app(ProspectAuditService::class)->repairSiteAudit($prospect);

        $prospect->refresh();
        $this->assertSame('pending', $prospect->audit_status);
        $this->assertNull($prospect->raw_a11y_payload);

        Queue::assertPushed(AuditSiteJob::class, fn (AuditSiteJob $job) => $job->prospect->id === $prospect->id);
    }
}
