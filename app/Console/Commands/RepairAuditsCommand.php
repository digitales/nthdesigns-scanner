<?php

namespace App\Console\Commands;

use App\Jobs\CaptureScreenshotJob;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Queries\FailedScreenshotQuery;
use App\Queries\FailedSiteAuditQuery;
use App\Queries\StuckSiteAuditQuery;
use App\Services\ProspectAuditService;
use App\Support\AuditingQueuePresence;
use App\Support\QueueDispatchDelay;
use App\Support\StaleAuditJobCloser;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairAuditsCommand extends Command
{
    protected $signature = 'scanner:repair-audits
                            {--execute : Close stale jobs and dispatch repairs}
                            {--search= : Limit to search ID}
                            {--prospect= : Limit to prospect ID}
                            {--limit= : Maximum items to dispatch per category}
                            {--delay=5 : Seconds between each dispatch}
                            {--stuck-after=15 : Minutes before pending/running counts as stale}
                            {--only= : Restrict to stuck, failed, or screenshots}
                            {--skip-screenshots : Skip screenshot repairs}';

    protected $description = 'Repair stuck site audits, retry failed site audits, and retry failed/stuck screenshots';

    public function handle(ProspectAuditService $audits): int
    {
        $searchId = $this->option('search') !== null ? (int) $this->option('search') : null;
        $prospectId = $this->option('prospect') !== null ? (int) $this->option('prospect') : null;
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $delay = max(0, (int) $this->option('delay'));
        $stuckAfter = max(1, (int) $this->option('stuck-after'));
        $only = $this->option('only');

        $includeStuck = $only === null || $only === 'stuck';
        $includeFailed = $only === null || $only === 'failed';
        $includeScreenshots = ($only === null || $only === 'screenshots') && ! $this->option('skip-screenshots');

        $stuck = $includeStuck
            ? StuckSiteAuditQuery::get($searchId, $prospectId, $limit, $stuckAfter)
            : collect();

        $stuckIds = $stuck->pluck('id')->all();

        $failed = $includeFailed
            ? FailedSiteAuditQuery::get($searchId, $prospectId, $limit, $stuckIds)
            : collect();

        $screenshots = $includeScreenshots
            ? FailedScreenshotQuery::get($searchId, $prospectId, $limit, $stuckAfter)
            : collect();

        if ($stuck->isEmpty() && $failed->isEmpty() && $screenshots->isEmpty()) {
            $this->info('Nothing to repair.');

            return self::SUCCESS;
        }

        $this->info("stuck: {$stuck->count()}");
        $this->info("failed: {$failed->count()}");
        $this->info("screenshots: {$screenshots->count()}");

        if (AuditingQueuePresence::skipsQueueCheck() && $stuck->isNotEmpty()) {
            $this->comment('Queue check skipped (cloud connection) — stuck detection uses age threshold only.');
        }

        $rows = $this->buildTableRows($stuck, $failed, $screenshots, $stuckAfter);

        $this->table(
            ['category', 'prospect_id', 'report_id', 'search_id', 'reason'],
            $rows,
        );

        $maxPerBatch = QueueDispatchDelay::maxJobsPerBatch($delay);
        $totalDispatches = $stuck->count() + $failed->count() + $screenshots->count();

        if ($maxPerBatch !== null && $totalDispatches > $maxPerBatch) {
            $this->warn("With --delay={$delay}, each run can queue at most {$maxPerBatch} job(s) on SQS (".QueueDispatchDelay::MAX_SECONDS.'s cap). Re-run until none remain.');
        }

        if (! $this->option('execute')) {
            $this->comment('Dry run — no changes made. Pass --execute to repair and dispatch jobs.');

            return self::SUCCESS;
        }

        $dispatchIndex = 0;
        $siteDispatched = 0;
        $screenshotDispatched = 0;

        foreach ($stuck as $prospect) {
            if ($maxPerBatch !== null && $dispatchIndex >= $maxPerBatch) {
                break;
            }

            StaleAuditJobCloser::closeRunning($prospect->id, 'accessibility');
            $audits->repairSiteAudit(
                $prospect->fresh(),
                suppressAutoReport: true,
                delaySeconds: QueueDispatchDelay::forIndex($dispatchIndex, $delay),
            );
            $dispatchIndex++;
            $siteDispatched++;
        }

        foreach ($failed as $prospect) {
            if ($maxPerBatch !== null && $dispatchIndex >= $maxPerBatch) {
                break;
            }

            StaleAuditJobCloser::closeRunning($prospect->id, 'accessibility');
            $audits->repairSiteAudit(
                $prospect->fresh(),
                suppressAutoReport: true,
                delaySeconds: QueueDispatchDelay::forIndex($dispatchIndex, $delay),
            );
            $dispatchIndex++;
            $siteDispatched++;
        }

        foreach ($screenshots as $report) {
            if ($maxPerBatch !== null && $dispatchIndex >= $maxPerBatch) {
                break;
            }

            StaleAuditJobCloser::closeRunning($report->prospect_id, 'screenshot');

            $pending = CaptureScreenshotJob::dispatch($report);

            $delaySeconds = QueueDispatchDelay::forIndex($dispatchIndex, $delay);
            if ($delaySeconds > 0) {
                $pending->delay(now()->addSeconds($delaySeconds));
            }

            $dispatchIndex++;
            $screenshotDispatched++;
        }

        $this->info("Dispatched {$siteDispatched} site audit(s), {$screenshotDispatched} screenshot(s).");

        return self::SUCCESS;
    }

    /**
     * @return list<array{0: string, 1: int, 2: string, 3: int, 4: string}>
     */
    private function buildTableRows(
        Collection $stuck,
        Collection $failed,
        Collection $screenshots,
        int $stuckAfter,
    ): array {
        $rows = [];

        foreach ($stuck as $prospect) {
            /** @var Prospect $prospect */
            $rows[] = [
                'stuck',
                $prospect->id,
                '—',
                $prospect->search_id,
                StuckSiteAuditQuery::reasonFor($prospect, $stuckAfter),
            ];
        }

        foreach ($failed as $prospect) {
            /** @var Prospect $prospect */
            $rows[] = [
                'failed',
                $prospect->id,
                '—',
                $prospect->search_id,
                FailedSiteAuditQuery::reasonFor($prospect),
            ];
        }

        foreach ($screenshots as $report) {
            /** @var ProspectReport $report */
            $rows[] = [
                'screenshots',
                $report->prospect_id,
                (string) $report->id,
                $report->prospect->search_id,
                FailedScreenshotQuery::reasonFor($report),
            ];
        }

        return $rows;
    }
}
