<?php

namespace App\Console\Commands;

use App\Jobs\ScanNicheJob;
use App\Models\NicheScan;
use Illuminate\Console\Command;

class RecalculateNicheScoresCommand extends Command
{
    protected $signature = 'niches:recalculate-scores {--dry-run : Preview score changes without writing}';

    protected $description = 'Recompute opportunity_score for complete niche scans using tiered result_count confidence';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $changed = 0;
        $unchanged = 0;
        $rows = [];

        NicheScan::query()
            ->where('status', 'complete')
            ->orderBy('id')
            ->chunkById(200, function ($scans) use ($dryRun, &$changed, &$unchanged, &$rows) {
                foreach ($scans as $scan) {
                    $newScore = ScanNicheJob::opportunityScore(
                        (float) $scan->avg_gbp_score,
                        (float) $scan->pct_no_website,
                        (float) $scan->pct_low_reviews,
                        (int) $scan->result_count,
                    );

                    if ((float) $scan->opportunity_score === $newScore) {
                        $unchanged++;

                        continue;
                    }

                    $rows[] = [
                        $scan->niche,
                        $scan->city,
                        $scan->result_count,
                        $scan->opportunity_score,
                        $newScore,
                    ];

                    if (! $dryRun) {
                        $scan->update(['opportunity_score' => $newScore]);
                    }

                    $changed++;
                }
            });

        if ($rows !== []) {
            $this->table(
                ['niche', 'city', 'results', 'old_score', 'new_score'],
                $rows,
            );
        }

        $this->info("Changed: {$changed}; unchanged: {$unchanged}.");

        if ($dryRun && $changed > 0) {
            $this->comment('Dry run — no changes written. Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }
}
