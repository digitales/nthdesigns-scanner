<?php

namespace Tests\Feature;

use App\Enums\AuditJobStatus;
use App\Enums\AuditStatus;
use App\Enums\ScanType;
use App\Jobs\CaptureScreenshotJob;
use App\Models\AuditJob;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use App\Models\User;
use App\Services\AuditErrorRecorder;
use App\Services\ScreenshotCaptureService;
use App\Services\ScreenshotStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class CaptureScreenshotJobTest extends TestCase
{
    use RefreshDatabase;

    private function reportWithWebsite(): ProspectReport
    {
        $user = User::factory()->create();
        $search = Search::factory()->create([
            'user_id' => $user->id,
            'scan_type' => ScanType::Combined,
        ]);
        $prospect = Prospect::factory()->create([
            'search_id' => $search->id,
            'website_url' => 'https://example.com',
            'audit_status' => AuditStatus::Complete,
        ]);

        return ProspectReport::factory()->create(['prospect_id' => $prospect->id]);
    }

    public function test_stores_desktop_screenshot_on_success(): void
    {
        Storage::fake('public');
        config(['scanner.reports_disk' => 'public']);

        $report = $this->reportWithWebsite();

        $this->mock(ScreenshotCaptureService::class, function ($mock) {
            $mock->shouldReceive('captureDesktop')
                ->once()
                ->andReturnUsing(function (string $url, string $localDir): string {
                    $path = $localDir.'/desktop.png';
                    file_put_contents($path, 'fake-png');

                    return $path;
                });
        });

        (new CaptureScreenshotJob($report))->handle(
            app(ScreenshotCaptureService::class),
            app(ScreenshotStorageService::class),
            app(AuditErrorRecorder::class),
        );

        $report->refresh();

        $this->assertNotEmpty($report->screenshot_paths['desktop'] ?? null);
        $this->assertDatabaseHas('audit_jobs', [
            'prospect_id' => $report->prospect_id,
            'job_type' => 'screenshot',
            'status' => 'complete',
        ]);
    }

    public function test_rethrows_after_recording_failure(): void
    {
        $report = $this->reportWithWebsite();

        $this->mock(ScreenshotCaptureService::class, function ($mock) {
            $mock->shouldReceive('captureDesktop')
                ->once()
                ->andThrow(new RuntimeException('browser down'));
        });

        try {
            (new CaptureScreenshotJob($report))->handle(
                app(ScreenshotCaptureService::class),
                app(ScreenshotStorageService::class),
                app(AuditErrorRecorder::class),
            );

            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('browser down', $e->getMessage());
        }

        $auditJob = AuditJob::query()
            ->where('prospect_id', $report->prospect_id)
            ->where('job_type', 'screenshot')
            ->first();

        $this->assertNotNull($auditJob);
        $this->assertSame(AuditJobStatus::Failed, $auditJob->status);
        $this->assertSame('browser down', $auditJob->error_message);
        $this->assertNull($report->fresh()->screenshot_paths['desktop'] ?? null);
    }

    public function test_skips_when_desktop_screenshot_already_stored(): void
    {
        $report = $this->reportWithWebsite();
        $report->update([
            'screenshot_paths' => ['desktop' => 'https://cdn.example/reports/desktop.png'],
        ]);

        $this->mock(ScreenshotCaptureService::class, function ($mock) {
            $mock->shouldNotReceive('captureDesktop');
        });

        (new CaptureScreenshotJob($report))->handle(
            app(ScreenshotCaptureService::class),
            app(ScreenshotStorageService::class),
            app(AuditErrorRecorder::class),
        );

        $this->assertDatabaseMissing('audit_jobs', [
            'prospect_id' => $report->prospect_id,
            'job_type' => 'screenshot',
        ]);
    }
}
