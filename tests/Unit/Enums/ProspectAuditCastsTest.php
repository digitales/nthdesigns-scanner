<?php

namespace Tests\Unit\Enums;

use App\Enums\AuditStatus;
use App\Models\Prospect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProspectAuditCastsTest extends TestCase
{
    use RefreshDatabase;

    public function test_prospect_audit_status_casts_to_pending_enum(): void
    {
        $prospect = Prospect::factory()->create([
            'audit_status' => 'pending',
        ]);

        $prospect->refresh();

        $this->assertInstanceOf(AuditStatus::class, $prospect->audit_status);
        $this->assertSame(AuditStatus::Pending, $prospect->audit_status);
    }
}
