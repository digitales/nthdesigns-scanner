<?php

namespace App\Console\Commands;

use App\Jobs\ValidateProspectJob;
use App\Services\ProspectValidationRevalidationService;
use Illuminate\Console\Command;

class RevalidateValidationPatternCommand extends Command
{
    protected $signature = 'validation:revalidate-pattern {pattern : Lowercase substring to re-validate against}';

    protected $description = 'Re-run prospect validation for prospects matching a franchise signal pattern';

    public function handle(ProspectValidationRevalidationService $revalidation): int
    {
        $pattern = strtolower(trim($this->argument('pattern')));

        if ($pattern === '') {
            $this->error('Pattern cannot be empty.');

            return self::FAILURE;
        }

        $count = 0;

        foreach ($revalidation->matchingProspects($pattern) as $prospect) {
            ValidateProspectJob::dispatch($prospect)
                ->delay(now()->addMilliseconds($count * 200));

            $count++;
        }

        $this->info("Queued validation for {$count} prospect(s) matching \"{$pattern}\".");

        return self::SUCCESS;
    }
}
