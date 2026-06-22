<?php

namespace Tests\Unit;

use App\Enums\AuditStatus;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Support\ProspectSiteScan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProspectSiteScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_classifies_fly_service_timeout_as_audit_service_error(): void
    {
        config(['scanner.audit_service_url' => 'https://nth-scanner-browser.fly.dev']);

        $message = 'cURL error 28: Operation timed out after 360000 milliseconds for https://nth-scanner-browser.fly.dev/audit';

        $this->assertTrue(ProspectSiteScan::isAuditServiceErrorMessage($message));
    }

    public function test_classifies_target_site_error_as_site_load(): void
    {
        config(['scanner.audit_service_url' => 'https://nth-scanner-browser.fly.dev']);

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'audit_status' => AuditStatus::Complete,
            'raw_a11y_payload' => [
                'url' => 'https://example.com',
                'error' => 'page.goto: Timeout 30000ms exceeded.',
                'violations' => [],
            ],
        ]);

        $issue = ProspectSiteScan::auditIssue($prospect);

        $this->assertSame('site_load', $issue['kind']);
        $this->assertSame('page.goto: Timeout 30000ms exceeded.', $issue['message']);
    }

    public function test_audit_issue_fields_split_service_and_site_errors(): void
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

        $fields = ProspectSiteScan::auditIssueFields($prospect);

        $this->assertSame('audit_service', $fields['audit_issue_kind']);
        $this->assertNull($fields['site_load_error']);
        $this->assertStringContainsString('fly.dev', $fields['audit_service_error']);
    }
}
