<?php

namespace Tests\Unit;

use App\Enums\AuditJobStatus;
use App\Enums\AuditStatus;
use App\Http\Resources\SearchProspectResource;
use App\Models\AuditJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Services\ProgressFlowService;
use App\Services\ReportBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchProspectResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_unreachable_flag_for_preflight_failure(): void
    {
        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'audit_status' => AuditStatus::Failed,
            'raw_a11y_payload' => [
                'url' => 'https://dead.example',
                'error' => 'Could not resolve host',
                'preflight_failed' => true,
            ],
        ]);

        AuditJob::create([
            'prospect_id' => $prospect->id,
            'job_type' => 'accessibility',
            'status' => AuditJobStatus::Failed,
            'error_message' => 'Could not resolve host',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $prospect->load('auditJobs');

        $formatted = SearchProspectResource::format(
            $prospect,
            $search,
            app(ReportBuilderService::class),
            app(ProgressFlowService::class),
        );

        $this->assertTrue($formatted['site_unreachable']);
        $this->assertSame('Could not resolve host', $formatted['audit_error']);
        $this->assertNull($formatted['site_load_error']);
        $this->assertNull($formatted['audit_service_error']);
    }

    public function test_audit_service_error_is_not_reported_as_site_load_error(): void
    {
        config(['scanner.audit_service_url' => 'https://nth-scanner-browser.fly.dev']);

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'audit_status' => AuditStatus::Complete,
            'raw_a11y_payload' => [
                'url' => 'https://example.com',
                'error' => 'cURL error 28: Operation timed out for https://nth-scanner-browser.fly.dev/audit',
                'violations' => [],
            ],
        ]);

        $formatted = SearchProspectResource::format(
            $prospect,
            $search,
            app(ReportBuilderService::class),
            app(ProgressFlowService::class),
        );

        $this->assertNull($formatted['audit_error']);
        $this->assertNull($formatted['site_load_error']);
        $this->assertSame('audit_service', $formatted['audit_issue_kind']);
        $this->assertStringContainsString('fly.dev', $formatted['audit_service_error']);
    }
}
