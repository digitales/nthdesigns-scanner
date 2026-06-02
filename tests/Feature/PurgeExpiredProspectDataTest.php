<?php

namespace Tests\Feature;

use App\Models\AuditJob;
use App\Models\AuditJobErrorDetail;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PurgeExpiredProspectDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_clears_expired_prospect_payloads_and_reports(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create([
            'search_id'       => $search->id,
            'expires_at'      => now()->subDay(),
            'raw_gbp_payload' => ['test' => true],
        ]);
        $report = ProspectReport::factory()->create([
            'prospect_id' => $prospect->id,
            'expires_at'  => now()->subDay(),
        ]);

        Artisan::call('scanner:purge-expired');

        $prospect->refresh();
        $this->assertNull($prospect->raw_gbp_payload);
        $this->assertDatabaseMissing('prospect_reports', ['id' => $report->id]);
    }

    public function test_purge_deletes_aged_audit_error_details_but_keeps_job_summary(): void
    {
        config(['scanner.audit_error_detail_retention_days' => 90]);

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);
        $job = AuditJob::create([
            'prospect_id'   => $prospect->id,
            'job_type'      => 'accessibility',
            'status'        => 'failed',
            'error_message' => 'Summary kept',
            'completed_at'  => now(),
        ]);
        $detail = AuditJobErrorDetail::create([
            'audit_job_id' => $job->id,
            'body'         => 'Full diagnostic body',
            'created_at'   => now()->subDays(91),
        ]);

        Artisan::call('scanner:purge-expired');

        $this->assertDatabaseMissing('audit_job_error_details', ['id' => $detail->id]);
        $this->assertDatabaseHas('audit_jobs', [
            'id'            => $job->id,
            'error_message' => 'Summary kept',
        ]);
    }
}
