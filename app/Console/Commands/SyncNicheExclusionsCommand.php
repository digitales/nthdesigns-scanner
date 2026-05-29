<?php

namespace App\Console\Commands;

use App\Services\NicheExclusionService;
use Illuminate\Console\Command;

class SyncNicheExclusionsCommand extends Command
{
    protected $signature = 'niches:sync-exclusions';

    protected $description = 'Auto-ignore niches whose best city has fewer than min_result_count Places results';

    public function handle(NicheExclusionService $exclusions): int
    {
        $changed = $exclusions->syncAllLowResultExclusions();
        $ignored = count($exclusions->ignoredLabels());

        $this->info("Updated {$changed} niche exclusion(s). {$ignored} niche(s) currently ignored.");

        return self::SUCCESS;
    }
}
