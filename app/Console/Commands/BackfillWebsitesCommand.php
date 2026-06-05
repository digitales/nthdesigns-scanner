<?php

namespace App\Console\Commands;

use App\Models\Prospect;
use App\Services\ProspectAuditService;
use App\Services\WebsiteDiscoveryService;
use App\Support\QueueDispatchDelay;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BackfillWebsitesCommand extends Command
{
    protected $signature = 'scanner:backfill-websites
                            {--execute : Run web search and apply matches (costs API quota per prospect)}
                            {--audit : Queue site audit when a URL is found (default: on with --execute)}
                            {--no-audit : Do not queue site audits after discovery}
                            {--search= : Limit to search ID}
                            {--prospect= : Limit to prospect ID}
                            {--limit= : Maximum prospects to process}
                            {--delay=5 : Seconds between each prospect (search + optional audit dispatch)}';

    protected $description = 'Discover website URLs for saved prospects missing one (Brave / CSE)';

    public function handle(
        WebsiteDiscoveryService $discovery,
        ProspectAuditService $audits,
    ): int {
        if (! $discovery->isEnabled()) {
            $this->error('Website discovery is disabled or not configured (set BRAVE_SEARCH_API_KEY or Google CSE credentials).');

            return self::FAILURE;
        }

        $searchId = $this->option('search') !== null ? (int) $this->option('search') : null;
        $prospectId = $this->option('prospect') !== null ? (int) $this->option('prospect') : null;
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $delay = max(0, (int) $this->option('delay'));
        $queueAudits = $this->shouldQueueAudits();

        $candidates = $this->fetchCandidates($searchId, $prospectId, $limit);

        if ($candidates->isEmpty()) {
            $this->info('No prospects match backfill criteria (empty website, combined/a11y search, city set, no GBP websiteUri).');

            return self::SUCCESS;
        }

        $rows = $candidates->map(fn (Prospect $prospect) => [
            $prospect->id,
            $prospect->business_name,
            $prospect->search_id,
            $prospect->search?->scan_type,
            $prospect->search?->city,
        ])->all();

        $this->table(
            ['prospect_id', 'business_name', 'search_id', 'scan_type', 'city'],
            $rows,
        );

        $total = $this->countCandidates($searchId, $prospectId);
        $this->info("Found {$total} prospect(s) eligible for website discovery.");

        if ($candidates->count() < $total) {
            $this->warn('Showing '.$candidates->count().' due to --limit; '.($total - $candidates->count()).' more match the criteria.');
        }

        $provider = config('scanner.website_discovery_provider', 'brave');
        $this->comment("Provider: {$provider}. Each processed prospect uses one search API request.");

        if (! $this->option('execute')) {
            $this->comment('Dry run — no API calls. Pass --execute to discover and save URLs.');
            if ($queueAudits) {
                $this->comment('With --execute, site audits will be queued for matches (--no-audit to skip).');
            }

            return self::SUCCESS;
        }

        $this->warnIfSqsBatchLimited($candidates->count(), $delay, $queueAudits);

        $matched = 0;
        $noMatch = 0;
        $auditsQueued = 0;
        $auditHeldBack = 0;

        foreach ($candidates as $index => $prospect) {
            if ($index > 0 && $delay > 0) {
                sleep($delay);
            }

            $prospect->loadMissing('search');
            $search = $prospect->search;

            if ($search === null) {
                continue;
            }

            $match = $discovery->discover($prospect, $search, $prospect->raw_gbp_payload ?? []);

            if ($match === null) {
                $noMatch++;
                $this->line("  [skip] #{$prospect->id} {$prospect->business_name} — no confident match");

                continue;
            }

            $prospect = $discovery->applyMatch($prospect, $search, $match);
            $matched++;
            $this->line("  [match] #{$prospect->id} {$prospect->business_name} → {$prospect->website_url} ({$match['confidence']})");

            if (! $queueAudits) {
                continue;
            }

            if (! $this->shouldAuditProspect($prospect)) {
                continue;
            }

            if ($this->auditBatchWouldExceedSqsCap($auditsQueued, $delay)) {
                $auditHeldBack++;

                continue;
            }

            try {
                $auditDelay = QueueDispatchDelay::forIndex($auditsQueued, $delay);
                $audits->queueSiteAudit($prospect, suppressAutoReport: true, delaySeconds: $auditDelay);
                $auditsQueued++;
            } catch (ValidationException $e) {
                $this->warn("  [audit skip] #{$prospect->id}: ".collect($e->errors())->flatten()->first());
            }
        }

        $this->newLine();
        $this->info("Matched {$matched} prospect(s), no match for {$noMatch}.");

        if ($queueAudits) {
            $this->info("Queued {$auditsQueued} site audit(s).");

            if ($auditHeldBack > 0) {
                $this->warn("{$auditHeldBack} audit(s) not queued — SQS caps DelaySeconds at ".QueueDispatchDelay::MAX_SECONDS.'. Re-run when the queue drains.');
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Prospect>
     */
    private function fetchCandidates(?int $searchId, ?int $prospectId, ?int $limit): Collection
    {
        $query = $this->eligibleQuery($searchId, $prospectId)->with('search');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    private function countCandidates(?int $searchId, ?int $prospectId): int
    {
        return $this->eligibleQuery($searchId, $prospectId)->count();
    }

    /**
     * @return Builder<Prospect>
     */
    private function eligibleQuery(?int $searchId, ?int $prospectId): Builder
    {
        return $this->baseQuery($searchId, $prospectId)
            ->where(function (Builder $query) {
                $query->whereNull('raw_gbp_payload')
                    ->orWhereNull('raw_gbp_payload->websiteUri')
                    ->orWhere('raw_gbp_payload->websiteUri', '');
            });
    }

    /**
     * @return Builder<Prospect>
     */
    private function baseQuery(?int $searchId, ?int $prospectId): Builder
    {
        return Prospect::query()
            ->where(fn (Builder $q) => $q->whereNull('website_url')->orWhere('website_url', ''))
            ->whereHas('search', fn (Builder $q) => $q
                ->whereIn('scan_type', ['accessibility_only', 'combined'])
                ->whereNotNull('city')
                ->where('city', '!=', ''))
            ->when($searchId !== null, fn (Builder $q) => $q->where('search_id', $searchId))
            ->when($prospectId !== null, fn (Builder $q) => $q->whereKey($prospectId))
            ->orderBy('id');
    }

    private function shouldQueueAudits(): bool
    {
        if ($this->option('no-audit')) {
            return false;
        }

        if ($this->option('audit')) {
            return true;
        }

        return (bool) $this->option('execute');
    }

    private function shouldAuditProspect(Prospect $prospect): bool
    {
        if (! in_array($prospect->search->scan_type, ['accessibility_only', 'combined'], true)) {
            return false;
        }

        if ($prospect->audit_status === 'pending') {
            return false;
        }

        return ! empty($prospect->website_url);
    }

    private function auditBatchWouldExceedSqsCap(int $auditsQueued, int $delay): bool
    {
        $maxPerBatch = QueueDispatchDelay::maxJobsPerBatch($delay);

        return $maxPerBatch !== null && $auditsQueued >= $maxPerBatch;
    }

    private function warnIfSqsBatchLimited(int $count, int $delay, bool $queueAudits): void
    {
        if (! $queueAudits) {
            return;
        }

        $maxPerBatch = QueueDispatchDelay::maxJobsPerBatch($delay);

        if ($maxPerBatch === null || $count <= $maxPerBatch) {
            return;
        }

        $this->warn("With --delay={$delay}, each run can queue at most {$maxPerBatch} audit(s) on SQS (".QueueDispatchDelay::MAX_SECONDS.'s cap). Re-run until none remain.');
    }
}
