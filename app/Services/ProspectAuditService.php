<?php

namespace App\Services;

use App\Enums\AuditStatus;
use App\Enums\ScanType;
use App\Jobs\AuditSiteJob;
use App\Models\Prospect;
use App\Support\AuditSiteJobDispatch;
use Illuminate\Validation\ValidationException;

class ProspectAuditService
{
    /**
     * Reset site-audit fields and queue {@see AuditSiteJob}. Does not touch GBP scores,
     * flags, or raw_gbp_payload (no Google Places API calls).
     */
    public function queueSiteAudit(Prospect $prospect, bool $suppressAutoReport = true, int $delaySeconds = 0): void
    {
        $prospect->loadMissing('search');

        if ($prospect->audit_status === AuditStatus::Pending) {
            throw ValidationException::withMessages([
                'website_url' => 'A site audit is already in progress.',
            ]);
        }

        if (empty($prospect->website_url)) {
            throw ValidationException::withMessages([
                'website_url' => 'Add a website URL before running a site audit.',
            ]);
        }

        $prospect->update(array_merge($this->auditResetFields(), [
            'suppress_auto_report' => $suppressAutoReport,
        ]));

        AuditSiteJobDispatch::dispatch($prospect->fresh(), $delaySeconds);
    }

    /**
     * Reset site-audit fields and queue {@see AuditSiteJob} for repair flows.
     * Unlike {@see queueSiteAudit()}, allows prospects already pending (stuck re-dispatch).
     */
    public function repairSiteAudit(Prospect $prospect, bool $suppressAutoReport = true, int $delaySeconds = 0): void
    {
        $prospect->loadMissing('search');

        if (empty($prospect->website_url)) {
            throw ValidationException::withMessages([
                'website_url' => 'Add a website URL before running a site audit.',
            ]);
        }

        if (! in_array($prospect->search->scan_type, [ScanType::AccessibilityOnly, ScanType::Combined], true)) {
            throw ValidationException::withMessages([
                'website_url' => 'This search type does not include site audits.',
            ]);
        }

        $prospect->update(array_merge($this->auditResetFields(), [
            'suppress_auto_report' => $suppressAutoReport,
        ]));

        AuditSiteJobDispatch::dispatch($prospect->fresh(), $delaySeconds);
    }

    /**
     * @return array<string, mixed>
     */
    public function auditResetFields(): array
    {
        return [
            'audit_status' => AuditStatus::Pending,
            'raw_a11y_payload' => null,
            'raw_lighthouse_payload' => null,
            'a11y_score' => 0,
            'a11y_flags' => null,
            'performance_score' => 0,
            'cms_detection' => null,
        ];
    }
}
