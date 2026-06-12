<?php

namespace Tests\Unit;

use App\Models\AuditJob;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use App\Services\ProgressFlowService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProgressFlowServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProgressFlowService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProgressFlowService;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function test_search_flow_auditing_counts_non_pending_prospects(): void
    {
        $search = Search::factory()->create([
            'status' => 'auditing',
            'total_found' => 3,
        ]);

        $prospects = collect([
            Prospect::factory()->for($search)->create(['audit_status' => 'pending']),
            Prospect::factory()->for($search)->create(['audit_status' => 'complete']),
            Prospect::factory()->for($search)->create(['audit_status' => 'failed']),
        ]);

        $flow = $this->service->searchFlow($search, $prospects);

        $this->assertSame('auditing', $flow['phase']);
        $this->assertSame(2, $flow['progress']);
        $this->assertSame(3, $flow['total']);
        $this->assertSame(67, $flow['percent']);
        $this->assertSame('Audited 2 of 3 prospects.', $flow['message']);
        $this->assertFalse($flow['search_complete']);
    }

    #[Test]
    public function test_search_flow_discovering_counts_prospect_total(): void
    {
        $search = Search::factory()->create([
            'status' => 'discovering',
            'total_found' => null,
        ]);

        $prospects = collect([
            Prospect::factory()->for($search)->create(['audit_status' => 'pending']),
            Prospect::factory()->for($search)->create(['audit_status' => 'pending']),
        ]);

        $flow = $this->service->searchFlow($search, $prospects);

        $this->assertSame('discovering', $flow['phase']);
        $this->assertSame(2, $flow['progress']);
        $this->assertSame(2, $flow['total']);
        $this->assertSame(100, $flow['percent']);
        $this->assertSame('Discovered 2 of 2 prospects.', $flow['message']);
    }

    #[Test]
    public function test_search_flow_queued_message_and_duration_bucket(): void
    {
        Carbon::setTestNow('2026-06-05 12:00:00');

        $search = Search::factory()->create([
            'status' => 'pending',
            'created_at' => Carbon::parse('2026-06-05 11:59:50'),
        ]);

        $flow = $this->service->searchFlow($search, collect());

        $this->assertSame('queued', $flow['phase']);
        $this->assertSame('Starting soon.', $flow['message']);
        $this->assertSame('<30s', $flow['duration_bucket']);
    }

    #[Test]
    public function test_search_flow_marks_terminal_search_complete(): void
    {
        $search = Search::factory()->create(['status' => 'complete', 'total_found' => 1]);
        $prospects = collect([
            Prospect::factory()->for($search)->create(['audit_status' => 'complete']),
        ]);

        $flow = $this->service->searchFlow($search, $prospects);

        $this->assertSame('complete', $flow['phase']);
        $this->assertTrue($flow['search_complete']);
        $this->assertSame('Completed 1 of 1 prospects.', $flow['message']);
    }

    #[Test]
    public function test_search_flow_duration_bucket_escalates_over_time(): void
    {
        Carbon::setTestNow('2026-06-05 12:10:00');

        $search = Search::factory()->create([
            'status' => 'auditing',
            'created_at' => Carbon::parse('2026-06-05 12:00:00'),
        ]);

        $flow = $this->service->searchFlow($search, collect());

        $this->assertSame('5m+', $flow['duration_bucket']);
    }

    #[Test]
    public function test_prospect_flow_pending_without_gbp_is_discovery(): void
    {
        $search = Search::factory()->create();
        $prospect = Prospect::factory()->for($search)->make([
            'audit_status' => 'pending',
            'raw_gbp_payload' => null,
            'gbp_score' => null,
        ]);

        $flow = $this->service->prospectFlow($prospect, $search);

        $this->assertSame('discovery', $flow['current_step']);
        $this->assertSame('Discovering business profile', $flow['status_message']);
    }

    #[Test]
    public function test_prospect_flow_pending_with_a11y_only_is_performance(): void
    {
        $search = Search::factory()->create();
        $prospect = Prospect::factory()->for($search)->make([
            'audit_status' => 'pending',
            'raw_gbp_payload' => ['rating' => 4],
            'raw_a11y_payload' => ['violations' => []],
            'raw_lighthouse_payload' => null,
        ]);

        $flow = $this->service->prospectFlow($prospect, $search);

        $this->assertSame('performance', $flow['current_step']);
        $this->assertSame('Running performance audit', $flow['status_message']);
    }

    #[Test]
    public function test_prospect_flow_pending_with_both_payloads_is_scoring(): void
    {
        $search = Search::factory()->create();
        $prospect = Prospect::factory()->for($search)->make([
            'audit_status' => 'pending',
            'raw_gbp_payload' => ['rating' => 4],
            'raw_a11y_payload' => ['violations' => []],
            'raw_lighthouse_payload' => ['categories' => []],
        ]);

        $flow = $this->service->prospectFlow($prospect, $search);

        $this->assertSame('scoring', $flow['current_step']);
        $this->assertSame('Combining audit scores', $flow['status_message']);
    }

    #[Test]
    public function test_prospect_flow_complete_without_report_is_report_step(): void
    {
        $search = Search::factory()->create(['scan_type' => 'combined']);
        $prospect = Prospect::factory()->for($search)->make(['audit_status' => 'complete']);
        $prospect->setRelation('report', null);

        $flow = $this->service->prospectFlow($prospect, $search);

        $this->assertSame('report', $flow['current_step']);
        $this->assertSame('Generating report', $flow['status_message']);
    }

    #[Test]
    public function test_prospect_flow_complete_with_report_is_done(): void
    {
        $search = Search::factory()->create();
        $prospect = Prospect::factory()->for($search)->make(['audit_status' => 'complete']);
        $prospect->setRelation('report', new ProspectReport);

        $flow = $this->service->prospectFlow($prospect, $search);

        $this->assertSame('done', $flow['current_step']);
        $this->assertSame('Complete', $flow['status_message']);
    }

    #[Test]
    public function test_prospect_flow_gbp_only_report_message(): void
    {
        $search = Search::factory()->create(['scan_type' => 'gbp_only']);
        $prospect = Prospect::factory()->for($search)->make(['audit_status' => 'complete']);
        $prospect->setRelation('report', null);

        $flow = $this->service->prospectFlow($prospect, $search);

        $this->assertSame('report', $flow['current_step']);
        $this->assertSame('Finalizing results', $flow['status_message']);
    }

    #[Test]
    public function test_prospect_flow_unreachable_failed_message(): void
    {
        $search = Search::factory()->create();
        $prospect = Prospect::factory()->for($search)->make([
            'audit_status' => 'failed',
            'raw_a11y_payload' => [
                'url' => 'https://dead.example',
                'error' => 'Could not resolve host',
                'preflight_failed' => true,
            ],
        ]);

        $flow = $this->service->prospectFlow($prospect, $search);

        $this->assertSame('Site unreachable', $flow['status_message']);
    }

    #[Test]
    public function test_prospect_flow_uses_latest_audit_job_started_at(): void
    {
        Carbon::setTestNow('2026-06-05 12:01:00');

        $search = Search::factory()->create();
        $prospect = Prospect::factory()->for($search)->make([
            'audit_status' => 'pending',
            'updated_at' => Carbon::parse('2026-06-05 11:00:00'),
        ]);

        $job = new AuditJob([
            'started_at' => Carbon::parse('2026-06-05 12:00:40'),
        ]);
        $prospect->setRelation('auditJobs', Collection::make([$job]));

        $flow = $this->service->prospectFlow($prospect, $search);

        $this->assertSame('2026-06-05T12:00:40+00:00', $flow['step_started_at']);
        $this->assertSame('<30s', $flow['step_duration_bucket']);
    }
}
