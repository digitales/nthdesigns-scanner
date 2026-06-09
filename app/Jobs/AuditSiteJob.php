<?php

namespace App\Jobs;

use App\Enums\AuditJobStatus;
use App\Enums\AuditJobType;
use App\Enums\AuditStatus;
use App\Models\AuditJob;
use App\Models\Prospect;
use App\Services\A11yScoringService;
use App\Services\AuditErrorRecorder;
use App\Services\AuditRunnerService;
use App\Services\CmsDetectionRunnerService;
use App\Services\ScreenshotStorageService;
use App\Services\SearchStatusService;
use App\Support\CmsDetectionPayload;
use App\Support\ScannerJobContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

#[Tries(2)]
#[Timeout(240)]
class AuditSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $backoff = 60;

    public function __construct(
        #[WithoutRelations]
        public Prospect $prospect,
    ) {}

    public function tries(): int
    {
        return 2;
    }

    public function handle(
        AuditRunnerService $auditRunner,
        A11yScoringService $scorer,
        SearchStatusService $searchStatus,
        ScreenshotStorageService $storage,
        AuditErrorRecorder $errorRecorder,
        CmsDetectionRunnerService $cmsRunner,
    ): void {
        ScannerJobContext::add(self::class, ['prospect_id' => $this->prospect->id]);

        $prospect = $this->prospect->fresh();

        if (! $prospect || ! $prospect->website_url) {
            return;
        }

        if ($prospect->audit_status !== AuditStatus::Pending) {
            return;
        }

        if ($auditRunner->shouldSkip()) {
            CombineScoresJob::dispatch($prospect);
            $searchStatus->refresh($prospect->search);

            return;
        }

        $auditJob = AuditJob::create([
            'prospect_id' => $prospect->id,
            'job_type' => AuditJobType::Accessibility,
            'status' => AuditJobStatus::Running,
            'attempts' => $this->attempts(),
            'started_at' => now(),
        ]);

        $screenshotDir = storage_path('app/temp/audit/'.$prospect->id);

        try {
            File::ensureDirectoryExists($screenshotDir);

            $payload = $auditRunner->run($prospect->website_url, $screenshotDir);
            $payload['violation_screenshots'] = $storage->storeViolationScreenshots(
                $prospect->id,
                $payload['violation_screenshots'] ?? [],
                $screenshotDir,
            );

            $scored = $scorer->score($payload);

            $updates = [
                'a11y_score' => $scored['score'],
                'a11y_flags' => $scored['flags'],
                'performance_score' => $scorer->extractPerformanceScore($payload),
                'raw_a11y_payload' => $payload,
                'raw_lighthouse_payload' => $payload['lighthouse'] ?? null,
            ];

            $cms = CmsDetectionPayload::fromAuditPayload($payload);

            if ($cms === null) {
                try {
                    $cms = $cmsRunner->run($prospect->website_url);
                } catch (\Throwable $e) {
                    Log::warning('AuditSiteJob CMS detection failed', [
                        'prospect_id' => $prospect->id,
                        'url' => $prospect->website_url,
                        'error' => $e->getMessage(),
                    ]);
                    $cms = null;
                }
            }

            if ($cms !== null) {
                $updates['cms_detection'] = $cms;
            }

            $prospect->update($updates);

            $auditJob->update([
                'status' => AuditJobStatus::Complete,
                'completed_at' => now(),
            ]);

            CombineScoresJob::dispatch($prospect->fresh());
        } catch (\Throwable $e) {
            Log::error('AuditSiteJob failed', [
                'prospect_id' => $prospect->id,
                'url' => $prospect->website_url,
                'error' => $e->getMessage(),
            ]);

            $auditJob->update([
                'status' => AuditJobStatus::Failed,
                'completed_at' => now(),
            ]);

            $errorRecorder->recordFailure($auditJob, $errorRecorder->formatThrowable($e));

            if ($this->attempts() >= $this->tries()) {
                $prospect->update(['audit_status' => AuditStatus::Failed]);
                $searchStatus->refresh($prospect->search);
            }

            throw $e;
        } finally {
            File::deleteDirectory($screenshotDir);
        }
    }
}
