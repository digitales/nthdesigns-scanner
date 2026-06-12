<?php

namespace App\Jobs;

use App\Models\Search;
use App\Services\MarketCpcLookupService;
use App\Support\ScannerJobContext;
use App\Support\SearchQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

#[Tries(2)]
class FetchSearchCpcJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $backoff = 60;

    public function __construct(
        #[WithoutRelations]
        public Search $search,
        public bool $force = false,
    ) {
        $this->onConnection(SearchQueue::connection());
        $this->onQueue(SearchQueue::NAME);
    }

    public function handle(MarketCpcLookupService $lookup): void
    {
        ScannerJobContext::add(self::class, ['search_id' => $this->search->id]);

        $search = $this->search->fresh(['user']);

        if (! $search || (! $this->force && $search->cpc_benchmark !== null)) {
            return;
        }

        if ($search->niche === null || $search->city === null || $search->user === null) {
            return;
        }

        $default = $lookup->fetchFromGoogleAds(
            $search->user,
            $search->niche,
            $search->city,
            $search->country ?? 'GB',
        );

        if ($default === null) {
            Log::info('google_ads.cpc_not_resolved', [
                'search_id' => $search->id,
                'niche' => $search->niche,
                'city' => $search->city,
            ]);

            return;
        }

        $search->update([
            'cpc_benchmark' => $default->cpc_benchmark,
            'cpc_source' => $default->cpc_source,
            'cpc_keywords' => $default->cpc_keywords,
            'cpc_geo_target' => $default->cpc_geo_target,
        ]);

        Log::info('google_ads.cpc_saved', [
            'search_id' => $search->id,
            'cpc_benchmark' => $default->cpc_benchmark,
            'keyword_count' => count($default->cpc_keywords ?? []),
        ]);
    }
}
