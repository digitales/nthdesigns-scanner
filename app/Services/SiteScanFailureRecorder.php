<?php

namespace App\Services;

use App\Enums\AuditJobStatus;
use App\Enums\AuditJobType;
use App\Enums\AuditStatus;
use App\Models\AuditJob;
use App\Models\Prospect;

class SiteScanFailureRecorder
{
    public function __construct(private AuditErrorRecorder $errorRecorder) {}

    public function recordPreflightFailure(Prospect $prospect, string $errorMessage): void
    {
        $url = (string) $prospect->website_url;

        $auditJob = AuditJob::create([
            'prospect_id' => $prospect->id,
            'job_type' => AuditJobType::Accessibility,
            'status' => AuditJobStatus::Running,
            'started_at' => now(),
        ]);

        $prospect->update([
            'audit_status' => AuditStatus::Failed,
            'a11y_score' => 0,
            'a11y_flags' => ['Site unreachable'],
            'performance_score' => 0,
            'raw_a11y_payload' => [
                'url' => $url,
                'error' => $errorMessage,
                'preflight_failed' => true,
                'violations' => [],
                'lighthouse' => null,
            ],
            'raw_lighthouse_payload' => null,
        ]);

        $auditJob->update([
            'status' => AuditJobStatus::Failed,
            'completed_at' => now(),
        ]);

        $this->errorRecorder->recordFailure($auditJob, $errorMessage);
    }
}
