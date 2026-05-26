<?php

namespace App\Jobs;

use App\Models\AuditJob;
use App\Models\ProspectReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class CaptureScreenshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 90;

    public function __construct(public ProspectReport $report) {}

    public function handle(): void
    {
        $report = $this->report->fresh(['prospect']);

        if (!$report?->prospect?->website_url) {
            return;
        }

        $auditJob = AuditJob::create([
            'prospect_id' => $report->prospect_id,
            'job_type'    => 'screenshot',
            'status'      => 'running',
            'started_at'  => now(),
        ]);

        try {
            $relativeDir = 'reports/'.$report->token;
            $absoluteDir = Storage::disk('public')->path($relativeDir);
            $scriptPath = base_path('scripts/screenshot.js');
            $nodeBinary = config('scanner.node_binary');

            $result = Process::timeout(90)->run([
                $nodeBinary,
                $scriptPath,
                $report->prospect->website_url,
                $absoluteDir,
            ]);

            if (!$result->successful()) {
                throw new \RuntimeException(trim($result->errorOutput() ?: $result->output()));
            }

            $output = json_decode($result->output(), true);

            if (!empty($output['error'])) {
                throw new \RuntimeException($output['error']);
            }

            $paths = [];

            if (!empty($output['desktop'])) {
                $paths['desktop'] = Storage::disk('public')->url($relativeDir.'/desktop.png');
            }

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
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);
        }
    }

    public function queue(): string
    {
        return 'auditing';
    }
}
