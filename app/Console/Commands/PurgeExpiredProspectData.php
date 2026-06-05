<?php

namespace App\Console\Commands;

use App\Models\AuditJobErrorDetail;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Services\ScreenshotStorageService;
use Illuminate\Console\Command;

class PurgeExpiredProspectData extends Command
{
    protected $signature = 'scanner:purge-expired {--execute : Delete expired payloads, reports, and aged error details}';

    protected $description = 'Purge expired prospect raw payloads, report assets, and aged audit error details';

    public function handle(ScreenshotStorageService $storage): int
    {
        $detailCutoff = now()->subDays(config('scanner.audit_error_detail_retention_days', 90));
        $detailCount = AuditJobErrorDetail::query()
            ->where('created_at', '<', $detailCutoff)
            ->count();

        $prospectCount = Prospect::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->count();

        $reportCount = ProspectReport::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->count();

        $this->table(
            ['Target', 'Count'],
            [
                ['Expired prospect payloads', (string) $prospectCount],
                ['Expired reports', (string) $reportCount],
                ['Audit error details older than retention', (string) $detailCount],
            ],
        );

        if (! $this->option('execute')) {
            $this->comment('Dry run — no data deleted. Pass --execute to purge.');

            return self::SUCCESS;
        }

        $purgedDetails = AuditJobErrorDetail::query()
            ->where('created_at', '<', $detailCutoff)
            ->delete();

        $purgedProspects = 0;
        Prospect::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->chunkById(100, function ($prospects) use (&$purgedProspects, $storage) {
                foreach ($prospects as $prospect) {
                    $prospect->update([
                        'raw_gbp_payload' => null,
                        'raw_a11y_payload' => null,
                        'raw_lighthouse_payload' => null,
                    ]);

                    $storage->deleteDirectory("prospects/{$prospect->id}");
                    $purgedProspects++;
                }
            });

        $purgedReports = 0;
        ProspectReport::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->chunkById(100, function ($reports) use (&$purgedReports, $storage) {
                foreach ($reports as $report) {
                    $storage->deleteDirectory('reports/'.$report->token);
                    $report->delete();
                    $purgedReports++;
                }
            });

        $this->info("Purged raw data for {$purgedProspects} prospect(s), {$purgedReports} expired report(s), and {$purgedDetails} audit error detail(s).");

        return self::SUCCESS;
    }
}
