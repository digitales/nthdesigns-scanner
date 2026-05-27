<?php

namespace App\Jobs;

use App\Models\AuditJob;
use App\Models\Prospect;
use App\Support\AuditingQueue;
use App\Services\A11yScoringService;
use App\Services\AuditRunnerService;
use App\Services\ScreenshotStorageService;
use App\Services\SearchStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class AuditSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 60;
    public int $timeout = 210;

    public function __construct(public Prospect $prospect)
    {
        AuditingQueue::apply($this);
    }

    public function handle(
        AuditRunnerService $auditRunner,
        A11yScoringService $scorer,
        SearchStatusService $searchStatus,
        ScreenshotStorageService $storage,
    ): void {
        $prospect = $this->prospect->fresh();

        if (!$prospect || !$prospect->website_url) {
            return;
        }

        if ($prospect->audit_status !== 'pending') {
            return;
        }

        if ($auditRunner->shouldSkip()) {
            CombineScoresJob::dispatch($prospect);
            $searchStatus->refresh($prospect->search);

            return;
        }

        $auditJob = AuditJob::create([
            'prospect_id' => $prospect->id,
            'job_type'    => 'accessibility',
            'status'      => 'running',
            'attempts'    => $this->attempts(),
            'started_at'  => now(),
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

            $prospect->update([
                'a11y_score'              => $scored['score'],
                'a11y_flags'              => $scored['flags'],
                'performance_score'       => $scorer->extractPerformanceScore($payload),
                'raw_a11y_payload'        => $payload,
                'raw_lighthouse_payload'  => $payload['lighthouse'] ?? null,
            ]);

            $auditJob->update([
                'status'       => 'complete',
                'completed_at' => now(),
            ]);

            CombineScoresJob::dispatch($prospect->fresh());
        } catch (\Throwable $e) {
            Log::error('AuditSiteJob failed', [
                'prospect_id' => $prospect->id,
                'url'         => $prospect->website_url,
                'error'       => $e->getMessage(),
            ]);

            $auditJob->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);

            $prospect->update(['audit_status' => 'failed']);

            $searchStatus->refresh($prospect->search);

            throw $e;
        } finally {
            File::deleteDirectory($screenshotDir);
        }
    }

}
