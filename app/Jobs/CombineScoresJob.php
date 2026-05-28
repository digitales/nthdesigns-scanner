<?php

namespace App\Jobs;

use App\Models\Prospect;
use App\Support\AuditingQueue;
use App\Services\CombineScoresService;
use App\Services\SearchStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CombineScoresJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Prospect $prospect)
    {
        AuditingQueue::apply($this);
    }

    public function handle(
        CombineScoresService $combiner,
        SearchStatusService $searchStatus,
    ): void {
        $prospect = $this->prospect->fresh();

        if (!$prospect) {
            return;
        }

        if ($prospect->audit_status === 'complete') {
            return;
        }

        $search = $prospect->search;
        $result = $combiner->combine($prospect, $search->scan_type);

        $auditStatus = match (true) {
            $prospect->audit_status === 'failed' => 'failed',
            config('scanner.audit_driver') === 'skip' && !empty($prospect->website_url) => 'skipped',
            empty($prospect->website_url) && in_array($search->scan_type, ['accessibility_only', 'combined'], true) => 'skipped',
            default => 'complete',
        };

        $prospect->update(array_merge($result, [
            'audit_status' => $auditStatus,
        ]));

        $prospect = $prospect->fresh();

        if ($prospect && in_array($prospect->audit_status, ['complete', 'skipped'], true)) {
            if ($prospect->suppress_auto_report) {
                $prospect->update(['suppress_auto_report' => false]);
            } else {
                GenerateProspectReportJob::dispatch($prospect);
            }
        }

        $searchStatus->refresh($search);
    }

}
