<?php

namespace App\Jobs;

use App\Models\AuditJob;
use App\Models\ProspectReport;
use App\Support\AuditingQueue;
use App\Services\AuditErrorRecorder;
use App\Services\ScreenshotCaptureService;
use App\Services\ScreenshotStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class CaptureScreenshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;

    /** @var list<int> */
    public array $backoff = [60, 120];

    public function __construct(public ProspectReport $report)
    {
        AuditingQueue::apply($this);
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('fly-browser-screenshot'))
                ->releaseAfter($this->timeout)
                ->expireAfter(600),
        ];
    }

    public function handle(
        ScreenshotCaptureService $capture,
        ScreenshotStorageService $storage,
        AuditErrorRecorder $errorRecorder,
    ): void {
        $report = $this->report->fresh(['prospect']);

        if (!$report?->prospect?->website_url) {
            return;
        }

        $paths = $report->screenshot_paths ?? [];

        if (! empty($paths['desktop'])) {
            return;
        }

        $auditJob = AuditJob::create([
            'prospect_id' => $report->prospect_id,
            'job_type'    => 'screenshot',
            'status'      => 'running',
            'started_at'  => now(),
        ]);

        $localDir = storage_path('app/temp/screenshots/'.$report->token);

        try {
            File::ensureDirectoryExists($localDir);

            $desktopPath = $capture->captureDesktop($report->prospect->website_url, $localDir);
            $relative = 'reports/'.$report->token.'/desktop.png';
            $paths = $report->screenshot_paths ?? [];
            $paths['desktop'] = $storage->storeLocalFile($relative, $desktopPath);

            $report->update(['screenshot_paths' => $paths]);

            $auditJob->update([
                'status'       => 'complete',
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('CaptureScreenshotJob failed', [
                'report_id' => $report->id,
                'error'     => $e->getMessage(),
            ]);

            $auditJob->update([
                'status'       => 'failed',
                'completed_at' => now(),
            ]);

            $errorRecorder->recordFailure($auditJob, $errorRecorder->formatThrowable($e));

            throw $e;
        } finally {
            File::deleteDirectory($localDir);
        }
    }

}
