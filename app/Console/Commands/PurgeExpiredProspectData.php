<?php

namespace App\Console\Commands;

use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Services\ScreenshotStorageService;
use Illuminate\Console\Command;

class PurgeExpiredProspectData extends Command
{
    protected $signature = 'scanner:purge-expired';

    protected $description = 'Purge expired prospect raw payloads and report assets';

    public function handle(ScreenshotStorageService $storage): int
    {
        $prospectCount = 0;
        $reportCount = 0;

        Prospect::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->chunkById(100, function ($prospects) use (&$prospectCount, $storage) {
                foreach ($prospects as $prospect) {
                    $prospect->update([
                        'raw_gbp_payload'        => null,
                        'raw_a11y_payload'       => null,
                        'raw_lighthouse_payload' => null,
                    ]);

                    $storage->deleteDirectory("prospects/{$prospect->id}");
                    $prospectCount++;
                }
            });

        ProspectReport::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->chunkById(100, function ($reports) use (&$reportCount, $storage) {
                foreach ($reports as $report) {
                    $storage->deleteDirectory('reports/'.$report->token);
                    $report->delete();
                    $reportCount++;
                }
            });

        $this->info("Purged raw data for {$prospectCount} prospect(s) and {$reportCount} expired report(s).");

        return self::SUCCESS;
    }
}
