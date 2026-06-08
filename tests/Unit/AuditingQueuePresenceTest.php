<?php

namespace Tests\Unit;

use App\Jobs\AuditSiteJob;
use App\Jobs\CaptureScreenshotJob;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use App\Models\User;
use App\Support\AuditingQueue;
use App\Support\AuditingQueuePresence;
use App\Support\ScannerConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditingQueuePresenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_connection_detects_pending_audit_site_job(): void
    {
        $this->useAuditingDatabaseQueue();

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id, 'scan_type' => 'combined']);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'website_url' => 'https://example.com',
        ]);

        $this->assertFalse(AuditingQueuePresence::hasPendingAuditSiteJob($prospect->id));

        AuditSiteJob::dispatch($prospect);

        $this->assertTrue(AuditingQueuePresence::hasPendingAuditSiteJob($prospect->id));
    }

    public function test_database_connection_detects_pending_screenshot_job(): void
    {
        $this->useAuditingDatabaseQueue();

        $report = ProspectReport::factory()->create();

        $this->assertFalse(AuditingQueuePresence::hasPendingScreenshotJob($report->id));

        CaptureScreenshotJob::dispatch($report);

        $this->assertTrue(AuditingQueuePresence::hasPendingScreenshotJob($report->id));
    }

    public function test_cloud_connection_skips_queue_check(): void
    {
        Config::set('scanner.auditing_queue_connection', 'cloud');
        ScannerConfig::registerQueueRoutes();

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);

        AuditSiteJob::dispatch($prospect);

        $this->assertFalse(AuditingQueuePresence::hasPendingAuditSiteJob($prospect->id));
        $this->assertTrue(AuditingQueuePresence::skipsQueueCheck());
    }

    public function test_ignores_completed_jobs_in_database(): void
    {
        $this->useAuditingDatabaseQueue();

        $user = User::factory()->create();
        $search = Search::factory()->create(['user_id' => $user->id]);
        $prospect = Prospect::factory()->create(['search_id' => $search->id]);

        DB::table('jobs')->insert([
            'queue' => AuditingQueue::NAME,
            'payload' => json_encode(['displayName' => AuditSiteJob::class]),
            'attempts' => 0,
            'reserved_at' => now()->timestamp,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $this->assertFalse(AuditingQueuePresence::hasPendingAuditSiteJob($prospect->id));
    }
}
