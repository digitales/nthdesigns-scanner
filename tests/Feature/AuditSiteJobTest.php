<?php

namespace Tests\Feature;

use App\Enums\AuditStatus;
use App\Enums\ScanType;
use App\Jobs\AuditSiteJob;
use App\Jobs\CombineScoresJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use App\Services\A11yScoringService;
use App\Services\AuditErrorRecorder;
use App\Services\AuditRunnerService;
use App\Services\CmsDetectionRunnerService;
use App\Services\ScreenshotStorageService;
use App\Services\SearchStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class AuditSiteJobTest extends TestCase
{
    use RefreshDatabase;

    private function pendingProspect(): Prospect
    {
        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'scan_type' => ScanType::Combined,
        ]);

        return Prospect::factory()->create([
            'search_id' => $search->id,
            'website_url' => 'https://example.com',
            'audit_status' => AuditStatus::Pending,
        ]);
    }

    public function test_defers_failed_status_until_tries_exhausted(): void
    {
        Queue::fake([CombineScoresJob::class]);

        $prospect = $this->pendingProspect();

        $this->mock(AuditRunnerService::class, function ($mock) {
            $mock->shouldReceive('shouldSkip')->andReturn(false);
            $mock->shouldReceive('run')->andThrow(new RuntimeException('timeout'));
        });

        $job = $this->getMockBuilder(AuditSiteJob::class)
            ->setConstructorArgs([$prospect])
            ->onlyMethods(['attempts'])
            ->getMock();
        $job->method('attempts')->willReturn(1);

        try {
            $job->handle(
                app(AuditRunnerService::class),
                app(A11yScoringService::class),
                app(SearchStatusService::class),
                app(ScreenshotStorageService::class),
                app(AuditErrorRecorder::class),
                app(CmsDetectionRunnerService::class),
            );
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('timeout', $e->getMessage());
        }

        $this->assertSame(AuditStatus::Pending, $prospect->fresh()->audit_status);
    }

    public function test_marks_failed_on_final_attempt(): void
    {
        Queue::fake([CombineScoresJob::class]);

        $prospect = $this->pendingProspect();

        $this->mock(AuditRunnerService::class, function ($mock) {
            $mock->shouldReceive('shouldSkip')->andReturn(false);
            $mock->shouldReceive('run')->andThrow(new RuntimeException('timeout'));
        });

        $job = $this->getMockBuilder(AuditSiteJob::class)
            ->setConstructorArgs([$prospect])
            ->onlyMethods(['attempts'])
            ->getMock();
        $job->method('attempts')->willReturn(2);

        try {
            $job->handle(
                app(AuditRunnerService::class),
                app(A11yScoringService::class),
                app(SearchStatusService::class),
                app(ScreenshotStorageService::class),
                app(AuditErrorRecorder::class),
                app(CmsDetectionRunnerService::class),
            );
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(AuditStatus::Failed, $prospect->fresh()->audit_status);
    }

    public function test_uses_cms_from_audit_payload_without_second_runner_call(): void
    {
        Queue::fake([CombineScoresJob::class]);

        $prospect = $this->pendingProspect();

        $this->mock(AuditRunnerService::class, function ($mock) {
            $mock->shouldReceive('shouldSkip')->andReturn(false);
            $mock->shouldReceive('run')->andReturn([
                'violations' => [],
                'pass_count' => 0,
                'incomplete_count' => 0,
                'violation_screenshots' => [],
                'cms' => [
                    'platform' => 'wordpress',
                    'confidence' => 'high',
                    'signals' => [],
                ],
            ]);
        });

        $this->mock(CmsDetectionRunnerService::class, function ($mock) {
            $mock->shouldNotReceive('run');
        });

        $this->mock(ScreenshotStorageService::class, function ($mock) {
            $mock->shouldReceive('storeViolationScreenshots')->andReturn([]);
        });

        (new AuditSiteJob($prospect))->handle(
            app(AuditRunnerService::class),
            app(A11yScoringService::class),
            app(SearchStatusService::class),
            app(ScreenshotStorageService::class),
            app(AuditErrorRecorder::class),
            app(CmsDetectionRunnerService::class),
        );

        $prospect->refresh();
        $this->assertSame('wordpress', $prospect->cms_detection['platform']);
    }

    public function test_skips_cms_fallback_when_audit_payload_has_error(): void
    {
        Queue::fake([CombineScoresJob::class]);

        $prospect = $this->pendingProspect();

        $this->mock(AuditRunnerService::class, function ($mock) {
            $mock->shouldReceive('shouldSkip')->andReturn(false);
            $mock->shouldReceive('run')->andReturn([
                'url' => 'https://example.com',
                'error' => 'Navigation timeout',
                'violations' => [],
                'pass_count' => 0,
                'incomplete_count' => 0,
                'violation_screenshots' => [],
                'lighthouse' => null,
            ]);
        });

        $this->mock(CmsDetectionRunnerService::class, function ($mock) {
            $mock->shouldNotReceive('run');
        });

        $this->mock(ScreenshotStorageService::class, function ($mock) {
            $mock->shouldReceive('storeViolationScreenshots')->andReturn([]);
        });

        (new AuditSiteJob($prospect))->handle(
            app(AuditRunnerService::class),
            app(A11yScoringService::class),
            app(SearchStatusService::class),
            app(ScreenshotStorageService::class),
            app(AuditErrorRecorder::class),
            app(CmsDetectionRunnerService::class),
        );

        $prospect->refresh();
        $this->assertNull($prospect->cms_detection);
        $this->assertSame(50, $prospect->a11y_score);
        Queue::assertPushed(CombineScoresJob::class);
    }

    public function test_job_timeout_covers_audit_and_cms_http_budget(): void
    {
        config([
            'scanner.audit_site_job_timeout' => 0,
            'scanner.audit_timeout' => 210,
            'scanner.cms_detect_timeout' => 90,
        ]);

        $prospect = $this->pendingProspect();

        $this->assertSame(330, (new AuditSiteJob($prospect))->timeout);
    }
}
