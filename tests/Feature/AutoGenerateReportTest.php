<?php

namespace Tests\Feature;

use App\Enums\AuditStatus;
use App\Enums\ScanType;
use App\Jobs\CombineScoresJob;
use App\Jobs\GenerateProspectReportJob;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use App\Models\User;
use App\Services\CombineScoresService;
use App\Services\SearchStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AutoGenerateReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_combine_scores_dispatches_report_generation(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id, 'scan_type' => ScanType::GbpOnly]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'gbp_score' => 70,
            'a11y_score' => 0,
            'combined_score' => 0,
            'audit_status' => AuditStatus::Pending,
        ]);

        $job = new CombineScoresJob($prospect);
        $job->handle(
            app(CombineScoresService::class),
            app(SearchStatusService::class),
        );

        Bus::assertDispatched(GenerateProspectReportJob::class, 1);

        $prospect->refresh();
        $this->assertSame(AuditStatus::Complete, $prospect->audit_status);
        $this->assertSame(70, $prospect->combined_score);
    }

    public function test_combine_scores_dispatches_report_after_clearing_suppress_flag(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id, 'scan_type' => ScanType::GbpOnly]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'gbp_score' => 70,
            'a11y_score' => 0,
            'combined_score' => 0,
            'audit_status' => AuditStatus::Pending,
            'suppress_auto_report' => true,
        ]);

        $job = new CombineScoresJob($prospect);
        $job->handle(
            app(CombineScoresService::class),
            app(SearchStatusService::class),
        );

        Bus::assertDispatched(GenerateProspectReportJob::class, 1);

        $prospect->refresh();
        $this->assertFalse($prospect->suppress_auto_report);
        $this->assertSame(AuditStatus::Complete, $prospect->audit_status);
    }

    public function test_combine_scores_skips_report_when_report_already_exists(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id, 'scan_type' => ScanType::GbpOnly]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'gbp_score' => 70,
            'a11y_score' => 0,
            'combined_score' => 0,
            'audit_status' => AuditStatus::Pending,
        ]);

        ProspectReport::factory()->create(['prospect_id' => $prospect->id]);

        $job = new CombineScoresJob($prospect);
        $job->handle(
            app(CombineScoresService::class),
            app(SearchStatusService::class),
        );

        Bus::assertNotDispatched(GenerateProspectReportJob::class);
        $this->assertSame(AuditStatus::Complete, $prospect->fresh()->audit_status);
    }

    public function test_combine_scores_does_not_reprocess_skipped_prospect(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id, 'scan_type' => ScanType::Combined]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'gbp_score' => 70,
            'combined_score' => 70,
            'audit_status' => AuditStatus::Skipped,
        ]);

        $job = new CombineScoresJob($prospect);
        $job->handle(
            app(CombineScoresService::class),
            app(SearchStatusService::class),
        );

        Bus::assertNotDispatched(GenerateProspectReportJob::class);
        $this->assertSame(70, $prospect->fresh()->combined_score);
    }
}
