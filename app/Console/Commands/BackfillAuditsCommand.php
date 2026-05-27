<?php

namespace App\Console\Commands;

use App\Jobs\AuditSiteJob;
use App\Models\Prospect;
use App\Support\IncompleteAuditQuery;
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

    public function handle(): int
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
            $prospect->audit_status,
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

        if (!$this->option('execute')) {
            $this->comment('Dry run — no changes made. Pass --execute to reset and dispatch jobs.');

            return self::SUCCESS;
        }

        $dispatched = 0;

        foreach ($prospects as $index => $prospect) {
            $prospect->update([
                'audit_status'           => 'pending',
                'raw_a11y_payload'       => null,
                'raw_lighthouse_payload' => null,
                'a11y_score'             => 0,
                'a11y_flags'             => null,
                'performance_score'      => 0,
            ]);

            AuditSiteJob::dispatch($prospect->fresh())
                ->delay(now()->addSeconds($index * $delay));

            $dispatched++;
        }

        $this->info("Dispatched {$dispatched} audit job(s).");

        return self::SUCCESS;
    }
}
