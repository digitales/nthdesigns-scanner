<?php

namespace App\Console\Commands;

use App\Jobs\QualifyProspectJob;
use App\Jobs\ValidateProspectJob;
use App\Models\Prospect;
use App\Queries\UnvalidatedProspectQuery;
use Illuminate\Console\Command;

class BackfillValidationCommand extends Command
{
    protected $signature = 'validation:backfill
                            {--execute : Dispatch jobs (default dry-run)}
                            {--search= : Limit to search ID}
                            {--prospect= : Limit to prospect ID}
                            {--limit= : Maximum prospects per run}
                            {--delay=200 : Milliseconds between each dispatch}
                            {--force-qualify : Dispatch QualifyProspectJob instead of ValidateProspectJob}';

    protected $description = 'Backfill prospect validation for records that have never been validated';

    public function handle(): int
    {
        $searchId = $this->option('search') !== null ? (int) $this->option('search') : null;
        $prospectId = $this->option('prospect') !== null ? (int) $this->option('prospect') : null;
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $delay = max(0, (int) $this->option('delay'));
        $forceQualify = (bool) $this->option('force-qualify');
        $action = $forceQualify ? 'qualify+validate' : 'validate';

        $total = UnvalidatedProspectQuery::query()
            ->when($searchId !== null, fn ($q) => $q->where('search_id', $searchId))
            ->when($prospectId !== null, fn ($q) => $q->whereKey($prospectId))
            ->count();

        $prospects = UnvalidatedProspectQuery::get($searchId, $prospectId, $limit);

        if ($prospects->isEmpty()) {
            $this->info('No unvalidated prospects found.');

            return self::SUCCESS;
        }

        $rows = $prospects->map(fn (Prospect $prospect) => [
            $prospect->id,
            $prospect->business_name,
            $prospect->search_id,
            $prospect->qualification_status ?? '—',
            $prospect->combined_score ?? '—',
            $action,
        ])->all();

        $this->table(
            ['prospect_id', 'business_name', 'search_id', 'qualification_status', 'combined_score', 'action'],
            $rows,
        );

        $remaining = $total - $prospects->count();
        $this->info("Found {$total} unvalidated prospect(s).");

        if ($remaining > 0) {
            $this->warn("Showing {$prospects->count()} due to --limit; {$remaining} more match the criteria.");
        }

        if (! $this->option('execute')) {
            $this->comment('Dry run — no changes made. Pass --execute to dispatch jobs.');

            return self::SUCCESS;
        }

        $dispatched = 0;

        foreach ($prospects as $index => $prospect) {
            $pending = $forceQualify
                ? QualifyProspectJob::dispatch($prospect)
                : ValidateProspectJob::dispatch($prospect);

            if ($delay > 0) {
                $pending->delay(now()->addMilliseconds($index * $delay));
            }

            $dispatched++;
        }

        $this->info("Queued {$dispatched} job(s).");

        return self::SUCCESS;
    }
}
