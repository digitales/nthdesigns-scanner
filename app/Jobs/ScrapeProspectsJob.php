<?php

namespace App\Jobs;

use App\Enums\SearchStatus;
use App\Models\Search;
use App\Services\BenchmarkNormalizer;
use App\Services\GooglePlacesService;
use App\Services\ProspectExclusionService;
use App\Support\ScannerJobContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

#[Tries(3)]
class ScrapeProspectsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $backoff = 30;

    public function __construct(
        #[WithoutRelations]
        public Search $search,
    ) {}

    public function handle(
        GooglePlacesService $places,
        ProspectExclusionService $exclusions,
        BenchmarkNormalizer $benchmarks,
    ): void {
        ScannerJobContext::add(self::class, ['search_id' => $this->search->id]);

        $this->search->update(['status' => SearchStatus::Discovering]);

        try {
            $placeIds = $exclusions->filterPlaceIds(
                $this->search->user_id,
                $places->searchByNicheAndCity(
                    $this->search->niche,
                    $this->search->city,
                    $this->search->country,
                ),
            );

            $benchmarkPlace = $places->getTopRankedInNiche(
                $this->search->niche,
                $this->search->city,
                $this->search->country,
            );

            if (! $benchmarkPlace) {
                Log::warning('ScrapeProspectsJob: no benchmark place returned', [
                    'search_id' => $this->search->id,
                ]);
            }

            $this->search->update([
                'total_found' => count($placeIds),
                'benchmark_snapshot' => $benchmarkPlace
                    ? $benchmarks->fromPlace($benchmarkPlace)
                    : null,
            ]);

            if (count($placeIds) === 0) {
                $this->search->update(['status' => SearchStatus::Complete]);

                return;
            }

            foreach ($placeIds as $placeId) {
                ScorePlaceJob::dispatch($this->search, $placeId);
            }

        } catch (\Throwable $e) {
            Log::error('ScrapeProspectsJob failed', [
                'search_id' => $this->search->id,
                'error' => $e->getMessage(),
            ]);
            $this->search->update(['status' => SearchStatus::Failed]);
            throw $e;
        }
    }
}
