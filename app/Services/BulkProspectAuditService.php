<?php

namespace App\Services;

use App\Enums\AuditStatus;
use App\Enums\ScanType;
use App\Models\Prospect;
use App\Support\AuditSiteJobDispatch;
use App\Support\QueueDispatchDelay;
use App\Support\StaleAuditJobCloser;
use Illuminate\Support\Collection;

class BulkProspectAuditService
{
    public function __construct(
        private ProspectAuditService $audits,
    ) {}

    /**
     * @param  Collection<int, Prospect>  $prospects  In operator selection order
     */
    public function dispatch(Collection $prospects, string $mode): BulkAuditResult
    {
        $delayStep = (int) config('scanner.audit_dispatch_stagger_seconds', 30);
        $maxBatch = QueueDispatchDelay::maxJobsPerBatch($delayStep);

        $skippedPending = 0;
        $skippedNoUrl = 0;
        $skippedNotFailed = 0;
        $queued = 0;
        $notQueuedDueToCap = 0;

        $eligible = [];

        foreach ($prospects as $prospect) {
            if (empty($prospect->website_url)) {
                $skippedNoUrl++;

                continue;
            }

            if ($mode === 'failed') {
                if ($prospect->audit_status === AuditStatus::Pending) {
                    $skippedPending++;

                    continue;
                }

                if ($prospect->audit_status !== AuditStatus::Failed) {
                    $skippedNotFailed++;

                    continue;
                }
            }

            $eligible[] = $prospect;
        }

        $dispatchIndex = 0;

        foreach ($eligible as $prospect) {
            if ($maxBatch !== null && $dispatchIndex >= $maxBatch) {
                $notQueuedDueToCap++;

                continue;
            }

            $extraDelay = QueueDispatchDelay::forIndex($dispatchIndex, $delayStep);

            if ($mode === 'force' && $prospect->audit_status === AuditStatus::Pending) {
                StaleAuditJobCloser::closeRunning($prospect->id, 'accessibility');
                $this->restartPendingAudit($prospect->fresh(), $extraDelay);
            } else {
                $this->audits->queueSiteAudit($prospect, suppressAutoReport: true, delaySeconds: $extraDelay);
            }

            $dispatchIndex++;
            $queued++;
        }

        return new BulkAuditResult(
            $queued,
            $skippedPending,
            $skippedNoUrl,
            $skippedNotFailed,
            $notQueuedDueToCap,
        );
    }

    private function restartPendingAudit(Prospect $prospect, int $extraDelay): void
    {
        $prospect->loadMissing('search');

        if (in_array($prospect->search->scan_type, [ScanType::AccessibilityOnly, ScanType::Combined], true)) {
            $this->audits->repairSiteAudit($prospect, suppressAutoReport: true, delaySeconds: $extraDelay);

            return;
        }

        $prospect->update(array_merge($this->audits->auditResetFields(), [
            'suppress_auto_report' => true,
        ]));

        AuditSiteJobDispatch::dispatch($prospect->fresh(), $extraDelay);
    }
}
