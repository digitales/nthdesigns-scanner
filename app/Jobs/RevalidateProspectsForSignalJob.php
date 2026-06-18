<?php

namespace App\Jobs;

use App\Models\ProspectValidationSignal;
use App\Services\ProspectValidationRevalidationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RevalidateProspectsForSignalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $signalId,
        public ?string $oldPattern = null,
    ) {
        $this->onConnection(config('queue.default'));
    }

    public function handle(ProspectValidationRevalidationService $revalidation): void
    {
        $signal = ProspectValidationSignal::query()->find($this->signalId);

        if ($signal === null) {
            return;
        }

        $index = 0;

        foreach ($revalidation->matchingProspects($signal->pattern, $this->oldPattern) as $prospect) {
            ValidateProspectJob::dispatch($prospect)
                ->delay(now()->addMilliseconds($index * 200));

            $index++;
        }
    }
}
