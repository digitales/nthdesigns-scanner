<?php

namespace App\Jobs;

use App\Enums\AuditStatus;
use App\Enums\ScanType;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Services\CombineScoresService;
use App\Services\SearchStatusService;
use App\Support\ScannerJobContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

#[Tries(3)]
class CombineScoresJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        #[WithoutRelations]
        public Prospect $prospect,
    ) {}

    public function handle(
        CombineScoresService $combiner,
        SearchStatusService $searchStatus,
    ): void {
        ScannerJobContext::add(self::class, ['prospect_id' => $this->prospect->id]);

        $prospect = $this->prospect->fresh();

        if (! $prospect) {
            return;
        }

        if (in_array($prospect->audit_status, [AuditStatus::Complete, AuditStatus::Skipped, AuditStatus::Failed], true)) {
            $searchStatus->refresh($prospect->search);

            return;
        }

        $search = $prospect->search;
        $result = $combiner->combineForProspect($prospect, $search->scan_type);

        $auditStatus = match (true) {
            $prospect->audit_status === AuditStatus::Failed => AuditStatus::Failed,
            config('scanner.audit_driver') === 'skip' && ! empty($prospect->website_url) => AuditStatus::Skipped,
            empty($prospect->website_url) && in_array($search->scan_type, [ScanType::AccessibilityOnly, ScanType::Combined], true) => AuditStatus::Skipped,
            default => AuditStatus::Complete,
        };

        $prospect->update(array_merge($result, [
            'audit_status' => $auditStatus,
        ]));

        $prospect = $prospect->fresh();

        if ($prospect && in_array($prospect->audit_status, [AuditStatus::Complete, AuditStatus::Skipped], true)) {
            if ($prospect->suppress_auto_report) {
                $prospect->update(['suppress_auto_report' => false]);
            }

            if (! ProspectReport::where('prospect_id', $prospect->id)->exists()) {
                GenerateProspectReportJob::dispatch($prospect);
            }
        }

        $searchStatus->refresh($search);
    }
}
