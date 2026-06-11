<?php

namespace Tests\Unit;

use App\Enums\AuditStatus;
use App\Enums\ScanType;
use App\Enums\SearchStatus;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Queries\StuckCombineScoresQuery;
use App\Queries\StuckReportQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StuckReportQueriesTest extends TestCase
{
    use RefreshDatabase;

    private function prospect(string $auditStatus, array $extra = []): Prospect
    {
        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'scan_type' => ScanType::Combined,
            'status' => SearchStatus::Auditing,
        ]);

        return Prospect::factory()->create(array_merge([
            'search_id' => $search->id,
            'website_url' => 'https://example.com',
            'audit_status' => $auditStatus,
        ], $extra));
    }

    public function test_stuck_report_query_matches_complete_without_report(): void
    {
        $prospect = $this->prospect(AuditStatus::Complete->value);

        $this->assertSame([$prospect->id], StuckReportQuery::ids());
    }

    public function test_stuck_combine_query_matches_pending_with_payload(): void
    {
        $prospect = $this->prospect(AuditStatus::Pending->value, [
            'raw_a11y_payload' => ['error' => 'timeout', 'violations' => []],
        ]);

        $this->assertSame([$prospect->id], StuckCombineScoresQuery::ids());
    }

    public function test_stuck_combine_query_ignores_pending_without_payload(): void
    {
        $this->prospect(AuditStatus::Pending->value);

        $this->assertSame([], StuckCombineScoresQuery::ids());
    }
}
