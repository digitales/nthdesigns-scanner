<?php

namespace App\Jobs;

use App\Models\Search;
use App\Support\SearchQueue;
use App\Services\BenchmarkNormalizer;
use App\Services\GooglePlacesService;
use App\Services\SearchStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScrapeProspectsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public Search $search)
    {
        SearchQueue::apply($this);
    }

    public function handle(GooglePlacesService $places, SearchStatusService $searchStatus): void
    {
        $this->search->update(['status' => 'discovering']);

        try {
            $placeIds = $places->searchByNicheAndCity(
                $this->search->niche,
                $this->search->city,
                $this->search->country,
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
                    ? (new BenchmarkNormalizer())->fromPlace($benchmarkPlace)
                    : null,
            ]);

            if (count($placeIds) === 0) {
                $this->search->update(['status' => 'complete']);

                return;
            }

            foreach ($placeIds as $placeId) {
                ScorePlaceJob::dispatch($this->search, $placeId);
            }

        } catch (\Throwable $e) {
            Log::error('ScrapeProspectsJob failed', [
                'search_id' => $this->search->id,
                'error'     => $e->getMessage(),
            ]);
            $this->search->update(['status' => 'failed']);
            throw $e;
        }
    }

}
