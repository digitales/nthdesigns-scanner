<?php

namespace App\Console\Commands;

use App\Jobs\CombineScoresJob;
use App\Jobs\GenerateProspectReportJob;
use App\Models\Prospect;
use App\Queries\StuckCombineScoresQuery;
use App\Queries\StuckReportQuery;
use App\Support\QueueDispatchDelay;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairReportsCommand extends Command
{
    protected $signature = 'scanner:repair-reports
                            {--execute : Dispatch repair jobs}
                            {--search= : Limit to search ID}
                            {--prospect= : Limit to prospect ID}
                            {--limit= : Maximum items to dispatch per category}
                            {--delay=5 : Seconds between each dispatch}
                            {--only= : Restrict to reports, combine, or all (default all)}';

    protected $description = 'Dispatch stuck CombineScoresJob or GenerateProspectReportJob work without re-running audits';

    public function handle(): int
    {
        $searchId = $this->option('search') !== null ? (int) $this->option('search') : null;
        $prospectId = $this->option('prospect') !== null ? (int) $this->option('prospect') : null;
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $delay = max(0, (int) $this->option('delay'));
        $only = $this->option('only');

        $includeReports = $only === null || $only === 'all' || $only === 'reports';
        $includeCombine = $only === null || $only === 'all' || $only === 'combine';

        if ($only !== null && ! in_array($only, ['reports', 'combine', 'all'], true)) {
            $this->error('--only must be reports, combine, or all');

            return self::FAILURE;
        }

        $reports = $includeReports
            ? StuckReportQuery::get($searchId, $prospectId, $limit)
            : collect();

        $combine = $includeCombine
            ? StuckCombineScoresQuery::get($searchId, $prospectId, $limit)
            : collect();

        if ($reports->isEmpty() && $combine->isEmpty()) {
            $this->info('Nothing to repair.');

            return self::SUCCESS;
        }

        $this->info("reports: {$reports->count()}");
        $this->info("combine: {$combine->count()}");

        $this->table(
            ['category', 'prospect_id', 'search_id', 'reason'],
            $this->buildTableRows($reports, $combine),
        );

        $maxPerBatch = QueueDispatchDelay::maxJobsPerBatch($delay);
        $totalDispatches = $reports->count() + $combine->count();

        if ($maxPerBatch !== null && $totalDispatches > $maxPerBatch) {
            $this->warn("With --delay={$delay}, each run can queue at most {$maxPerBatch} job(s) on SQS (".QueueDispatchDelay::MAX_SECONDS.'s cap). Re-run until none remain.');
        }

        if (! $this->option('execute')) {
            $this->comment('Dry run — no changes made. Pass --execute to dispatch jobs.');

            return self::SUCCESS;
        }

        $dispatchIndex = 0;
        $combineDispatched = 0;
        $reportDispatched = 0;

        foreach ($combine as $prospect) {
            if ($maxPerBatch !== null && $dispatchIndex >= $maxPerBatch) {
                break;
            }

            CombineScoresJob::dispatch($prospect->fresh())
                ->delay(now()->addSeconds(QueueDispatchDelay::forIndex($dispatchIndex, $delay)));

            $dispatchIndex++;
            $combineDispatched++;
        }

        foreach ($reports as $prospect) {
            if ($maxPerBatch !== null && $dispatchIndex >= $maxPerBatch) {
                break;
            }

            GenerateProspectReportJob::dispatch($prospect->fresh())
                ->delay(now()->addSeconds(QueueDispatchDelay::forIndex($dispatchIndex, $delay)));

            $dispatchIndex++;
            $reportDispatched++;
        }

        $this->info("Dispatched {$combineDispatched} combine job(s), {$reportDispatched} report job(s).");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Prospect>  $reports
     * @param  Collection<int, Prospect>  $combine
     * @return list<array{0: string, 1: int, 2: int, 3: string}>
     */
    private function buildTableRows(Collection $reports, Collection $combine): array
    {
        $rows = [];

        foreach ($combine as $prospect) {
            /** @var Prospect $prospect */
            $rows[] = [
                'combine',
                $prospect->id,
                $prospect->search_id,
                StuckCombineScoresQuery::reasonFor($prospect),
            ];
        }

        foreach ($reports as $prospect) {
            /** @var Prospect $prospect */
            $rows[] = [
                'reports',
                $prospect->id,
                $prospect->search_id,
                StuckReportQuery::reasonFor($prospect),
            ];
        }

        return $rows;
    }
}
