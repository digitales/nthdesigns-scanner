<?php

namespace App\Console\Commands;

use App\Jobs\DetectCmsJob;
use App\Models\Prospect;
use App\Support\QueueDispatchDelay;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class BackfillCmsCommand extends Command
{
    protected $signature = 'scanner:backfill-cms
                            {--execute : Dispatch DetectCmsJob for matching prospects}
                            {--search= : Limit to search ID}
                            {--prospect= : Limit to prospect ID}
                            {--limit= : Maximum prospects to dispatch}
                            {--delay=5 : Seconds between each dispatch}';

    protected $description = 'Find prospects with a website but no CMS detection and queue DetectCmsJob';

    public function handle(): int
    {
        $searchId = $this->option('search') !== null ? (int) $this->option('search') : null;
        $prospectId = $this->option('prospect') !== null ? (int) $this->option('prospect') : null;
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $delay = max(0, (int) $this->option('delay'));

        $query = $this->query($searchId, $prospectId);
        $total = (clone $query)->count();
        $prospects = $this->fetch($searchId, $prospectId, $limit);

        if ($prospects->isEmpty()) {
            $this->info('No prospects missing CMS detection.');

            return self::SUCCESS;
        }

        $rows = $prospects->map(fn (Prospect $prospect) => [
            $prospect->id,
            $prospect->business_name,
            $prospect->search_id,
            $prospect->website_url,
        ])->all();

        $this->table(
            ['prospect_id', 'business_name', 'search_id', 'website_url'],
            $rows,
        );

        $remaining = $total - $prospects->count();
        $this->info("Found {$total} prospect(s) missing CMS detection.");

        if ($remaining > 0) {
            $this->warn("Showing {$prospects->count()} due to --limit; {$remaining} more match the criteria.");
        }

        $this->warnIfSqsBatchLimited($prospects->count(), $delay);

        if (! $this->option('execute')) {
            $this->comment('Dry run — no jobs dispatched. Pass --execute to queue DetectCmsJob.');

            return self::SUCCESS;
        }

        [$toDispatch, $heldBack] = $this->applySqsBatchLimit($prospects, $delay);
        $dispatched = 0;

        foreach ($toDispatch as $index => $prospect) {
            $dispatch = DetectCmsJob::dispatch($prospect->fresh());
            $delaySeconds = QueueDispatchDelay::forIndex($index, $delay);

            if ($delaySeconds > 0) {
                $dispatch->delay(now()->addSeconds($delaySeconds));
            }

            $dispatched++;
        }

        $this->info("Dispatched {$dispatched} DetectCmsJob(s).");

        if ($heldBack > 0) {
            $this->warn("{$heldBack} prospect(s) not queued — SQS caps DelaySeconds at ".QueueDispatchDelay::MAX_SECONDS.'. Re-run this command when the queue drains.');
        }

        return self::SUCCESS;
    }

    /**
     * @return Builder<Prospect>
     */
    private function query(?int $searchId, ?int $prospectId): Builder
    {
        return Prospect::query()
            ->whereNotNull('website_url')
            ->where('website_url', '!=', '')
            ->whereNull('cms_detection')
            ->when($searchId !== null, fn (Builder $q) => $q->where('search_id', $searchId))
            ->when($prospectId !== null, fn (Builder $q) => $q->whereKey($prospectId))
            ->orderBy('id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Prospect>
     */
    private function fetch(?int $searchId, ?int $prospectId, ?int $limit)
    {
        $query = $this->query($searchId, $prospectId);

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    private function warnIfSqsBatchLimited(int $count, int $delay): void
    {
        $maxPerBatch = QueueDispatchDelay::maxJobsPerBatch($delay);

        if ($maxPerBatch === null || $count <= $maxPerBatch) {
            return;
        }

        $this->warn("With --delay={$delay}, each run can queue at most {$maxPerBatch} job(s) on SQS (".QueueDispatchDelay::MAX_SECONDS.'s cap). Re-run until none remain.');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Prospect>  $prospects
     * @return array{0: \Illuminate\Database\Eloquent\Collection<int, Prospect>, 1: int}
     */
    private function applySqsBatchLimit($prospects, int $delay): array
    {
        $maxPerBatch = QueueDispatchDelay::maxJobsPerBatch($delay);

        if ($maxPerBatch === null || $prospects->count() <= $maxPerBatch) {
            return [$prospects, 0];
        }

        return [$prospects->take($maxPerBatch)->values(), $prospects->count() - $maxPerBatch];
    }
}
