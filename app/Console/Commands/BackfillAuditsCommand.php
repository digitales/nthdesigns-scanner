<?php

namespace App\Console\Commands;

use App\Models\Prospect;
use App\Queries\IncompleteAuditQuery;
use App\Services\ProspectAuditService;
use App\Support\QueueDispatchDelay;
use Illuminate\Console\Command;

class BackfillAuditsCommand extends Command
{
    protected $signature = 'scanner:backfill-audits
                            {--execute : Reset prospects and dispatch audit jobs}
                            {--search= : Limit to search ID}
                            {--prospect= : Limit to prospect ID}
                            {--limit= : Maximum prospects to dispatch}
                            {--delay=5 : Seconds between each dispatch}';

    protected $description = 'Find prospects with incomplete audit payloads and re-queue audits';

    public function handle(ProspectAuditService $audits): int
    {
        $searchId = $this->option('search') !== null ? (int) $this->option('search') : null;
        $prospectId = $this->option('prospect') !== null ? (int) $this->option('prospect') : null;
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $delay = max(0, (int) $this->option('delay'));

        $total = IncompleteAuditQuery::query()
            ->when($searchId !== null, fn ($q) => $q->where('search_id', $searchId))
            ->when($prospectId !== null, fn ($q) => $q->whereKey($prospectId))
            ->count();

        $prospects = IncompleteAuditQuery::get($searchId, $prospectId, $limit);

        if ($prospects->isEmpty()) {
            $this->info('No incomplete audits found.');

            return self::SUCCESS;
        }

        $rows = $prospects->map(fn (Prospect $prospect) => [
            $prospect->id,
            $prospect->business_name,
            $prospect->search_id,
            $prospect->audit_status?->value,
            IncompleteAuditQuery::reasonFor($prospect),
        ])->all();

        $this->table(
            ['prospect_id', 'business_name', 'search_id', 'audit_status', 'reason'],
            $rows,
        );

        $remaining = $total - $prospects->count();
        $this->info("Found {$total} incomplete audit(s).");

        if ($remaining > 0) {
            $this->warn("Showing {$prospects->count()} due to --limit; {$remaining} more match the criteria.");
        }

        $maxPerBatch = QueueDispatchDelay::maxJobsPerBatch($delay);

        if ($maxPerBatch !== null && $prospects->count() > $maxPerBatch) {
            $this->warn("With --delay={$delay}, each run can queue at most {$maxPerBatch} job(s) on SQS (".QueueDispatchDelay::MAX_SECONDS.'s cap). Re-run until none remain.');
        }

        if (! $this->option('execute')) {
            $this->comment('Dry run — no changes made. Pass --execute to reset and dispatch jobs.');

            return self::SUCCESS;
        }

        $toDispatch = $maxPerBatch !== null && $prospects->count() > $maxPerBatch
            ? $prospects->take($maxPerBatch)->values()
            : $prospects;
        $heldBack = $prospects->count() - $toDispatch->count();
        $dispatched = 0;

        foreach ($toDispatch as $index => $prospect) {
            $audits->queueSiteAudit(
                $prospect->fresh(),
                suppressAutoReport: true,
                delaySeconds: QueueDispatchDelay::forIndex($index, $delay),
            );

            $dispatched++;
        }

        $this->info("Dispatched {$dispatched} audit job(s).");

        if ($heldBack > 0) {
            $this->warn("{$heldBack} prospect(s) not queued — SQS caps DelaySeconds at ".QueueDispatchDelay::MAX_SECONDS.'. Re-run this command when the queue drains.');
        }

        return self::SUCCESS;
    }
}
