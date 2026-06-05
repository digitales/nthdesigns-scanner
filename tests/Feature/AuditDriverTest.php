<?php

namespace Tests\Feature;

use App\Jobs\AuditSiteJob;
use App\Jobs\CombineScoresJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Services\A11yScoringService;
use App\Services\AuditErrorRecorder;
use App\Services\AuditRunnerService;
use App\Services\CmsDetectionRunnerService;
use App\Services\CombineScoresService;
use App\Services\ScreenshotStorageService;
use App\Services\SearchStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AuditDriverTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_site_job_skips_to_combine_when_driver_is_skip(): void
    {
        Bus::fake();
        Config::set('scanner.audit_driver', 'skip');

        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'scan_type' => 'combined',
        ]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'website_url' => 'https://example.com',
            'audit_status' => 'pending',
        ]);

        $job = new AuditSiteJob($prospect);
        $job->handle(
            app(AuditRunnerService::class),
            app(A11yScoringService::class),
            app(SearchStatusService::class),
            app(ScreenshotStorageService::class),
            app(AuditErrorRecorder::class),
            app(CmsDetectionRunnerService::class),
        );

        Bus::assertDispatched(CombineScoresJob::class);
        $this->assertDatabaseMissing('audit_jobs', [
            'prospect_id' => $prospect->id,
            'job_type' => 'accessibility',
        ]);
    }

    public function test_combine_scores_marks_skipped_when_audit_driver_is_skip(): void
    {
        Bus::fake();
        Config::set('scanner.audit_driver', 'skip');

        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'scan_type' => 'combined',
        ]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'website_url' => 'https://example.com',
            'gbp_score' => 40,
            'audit_status' => 'pending',
        ]);

        $job = new CombineScoresJob($prospect);
        $job->handle(
            app(CombineScoresService::class),
            app(SearchStatusService::class),
        );

        $prospect->refresh();
        $this->assertSame('skipped', $prospect->audit_status);
    }
}
