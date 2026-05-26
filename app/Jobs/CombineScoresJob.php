<?php

namespace App\Jobs;

use App\Models\Prospect;
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

    public function __construct(public Prospect $prospect) {}

    public function handle(
        CombineScoresService $combiner,
        SearchStatusService $searchStatus,
    ): void {
        $prospect = $this->prospect->fresh();

        if (!$prospect) {
            return;
        }

        if (in_array($prospect->audit_status, ['complete', 'skipped'], true)) {
            return;
        }

        $search = $prospect->search;
        $result = $combiner->combine($prospect, $search->scan_type);

        $prospect->update(array_merge($result, [
            'audit_status' => $prospect->audit_status === 'failed' ? 'failed' : 'complete',
        ]));

        $searchStatus->refresh($search);
    }

    public function queue(): string
    {
        return 'auditing';
    }
}
