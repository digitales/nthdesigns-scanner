<?php

namespace App\Jobs;

use App\Models\AuditJob;
use App\Models\Prospect;
use App\Services\A11yScoringService;
use App\Services\SearchStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class AuditSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 60;
    public int $timeout = 150;

    public function __construct(public Prospect $prospect) {}

    public function handle(
        A11yScoringService $scorer,
        SearchStatusService $searchStatus,
    ): void {
        $prospect = $this->prospect->fresh();

        if (!$prospect || !$prospect->website_url) {
            return;
        }

        if ($prospect->audit_status !== 'pending') {
            return;
        }

        $auditJob = AuditJob::create([
            'prospect_id' => $prospect->id,
            'job_type'    => 'accessibility',
            'status'      => 'running',
            'attempts'    => $this->attempts(),
            'started_at'  => now(),
        ]);

        try {
            $payload = $this->runAuditScript($prospect->website_url);
            $scored  = $scorer->score($payload);

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

            CombineScoresJob::dispatch($prospect->fresh())->onQueue('auditing');
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
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function runAuditScript(string $url): array
    {
        $nodeBinary = config('scanner.node_binary');
        $scriptPath = config('scanner.audit_script_path');
        $timeout = config('scanner.audit_timeout');
        $lighthouseBinary = config('scanner.lighthouse_binary');

        $result = Process::timeout($timeout)
            ->env([
                'LIGHTHOUSE_BINARY' => $lighthouseBinary,
            ])
            ->run([$nodeBinary, $scriptPath, $url]);

        if (!$result->successful()) {
            throw new \RuntimeException(
                'Audit script failed: '.trim($result->errorOutput() ?: $result->output())
            );
        }

        $payload = json_decode($result->output(), true);

        if (!is_array($payload)) {
            throw new \RuntimeException('Audit script returned invalid JSON');
        }

        return $payload;
    }

    public function queue(): string
    {
        return 'auditing';
    }
}
