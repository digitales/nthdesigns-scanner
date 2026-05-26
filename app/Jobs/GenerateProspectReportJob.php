<?php

namespace App\Jobs;

use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Services\GooglePlacesService;
use App\Services\ReportBuilderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class GenerateProspectReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(public Prospect $prospect) {}

    public function handle(
        GooglePlacesService $places,
        ReportBuilderService $builder,
    ): void {
        $prospect = $this->prospect->fresh();

        if (!$prospect) {
            return;
        }

        $search = $prospect->search;

        $benchmark = $places->getTopRankedInNiche(
            $search->niche,
            $search->city,
            $search->country,
        );

        $reportData = $builder->build($prospect, $benchmark);
        $expiryDays = config('scanner.report_expiry_days', 30);

        $existing = ProspectReport::where('prospect_id', $prospect->id)->first();

        $report = ProspectReport::updateOrCreate(
            ['prospect_id' => $prospect->id],
            [
                'token'              => $existing?->token ?? (string) Str::uuid(),
                'benchmark_place_id' => $benchmark['id'] ?? null,
                'report_data'        => $reportData,
                'expires_at'         => now()->addDays($expiryDays),
            ]
        );

        if ($prospect->website_url) {
            CaptureScreenshotJob::dispatch($report)->onQueue('auditing');
        }
    }

    public function queue(): string
    {
        return 'auditing';
    }
}
