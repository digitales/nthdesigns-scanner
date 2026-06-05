<?php

namespace App\Jobs;

use App\Models\Prospect;
use App\Models\Search;
use App\Support\AuditingQueue;
use App\Models\ProspectReport;
use App\Services\BenchmarkNormalizer;
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

    public function __construct(public Prospect $prospect)
    {
        AuditingQueue::apply($this);
    }

    public function handle(
        GooglePlacesService $places,
        ReportBuilderService $builder,
    ): void {
        $prospect = $this->prospect->fresh(['search.user.setting']);

        if (!$prospect) {
            return;
        }

        $search = $prospect->search;
        $benchmark = $this->resolveBenchmark($places, $search, $prospect);

        $reportData = $builder->build($prospect, $benchmark);
        $expiryDays = config('scanner.report_expiry_days', 30);

        $existing = ProspectReport::where('prospect_id', $prospect->id)->first();

        $report = ProspectReport::updateOrCreate(
            ['prospect_id' => $prospect->id],
            [
                'token'              => $existing?->token ?? (string) Str::uuid(),
                'benchmark_place_id' => $benchmark['place_id'] ?? null,
                'report_data'        => $reportData,
                'expires_at'         => now()->addDays($expiryDays),
            ]
        );

        $paths = $report->screenshot_paths ?? [];

        if ($prospect->website_url && empty($paths['desktop'])) {
            CaptureScreenshotJob::dispatch($report);
        }
    }

    /**
     * Prefer the search benchmark captured at scrape time; never compare a prospect to itself.
     *
     * @return array<string, mixed>|null Normalized benchmark or null when none is available
     */
    private function resolveBenchmark(
        GooglePlacesService $places,
        Search $search,
        Prospect $prospect,
    ): ?array {
        $snapshot = $search->benchmark_snapshot;

        if ($snapshot && ($snapshot['place_id'] ?? null) !== $prospect->place_id) {
            return $snapshot;
        }

        if (blank($search->niche) || blank($search->city)) {
            return null;
        }

        $place = $places->getTopRankedInNiche(
            $search->niche,
            $search->city,
            $search->country,
            $prospect->place_id,
        );

        return $place ? (new BenchmarkNormalizer())->fromPlace($place) : null;
    }

}
