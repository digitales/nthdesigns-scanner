<?php

namespace App\Jobs;

use App\Models\Prospect;
use App\Services\ProspectValidatorService;
use App\Support\ScannerJobContext;
use App\Support\SearchQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

#[Tries(2)]
#[Timeout(15)]
class ValidateProspectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        #[WithoutRelations]
        public Prospect $prospect,
        public bool $chainQualification = false,
        public bool $forceQualification = false,
    ) {
        $this->onConnection(SearchQueue::connection());
        $this->onQueue(SearchQueue::NAME);
    }

    public function handle(ProspectValidatorService $service): void
    {
        ScannerJobContext::add(self::class, ['prospect_id' => $this->prospect->id]);

        $prospect = $this->prospect->fresh();

        if (! $prospect) {
            return;
        }

        $service->validate($prospect);

        if (! $this->chainQualification) {
            return;
        }

        $prospect = $prospect->fresh();

        if (! $prospect) {
            return;
        }

        if ($this->forceQualification || ! $service->shouldSkipQualification($prospect)) {
            QualifyProspectJob::dispatch($prospect);
        }
    }
}
