<?php

namespace App\Jobs;

use App\Models\Search;
use App\Services\GoogleAds\GoogleAdsKeywordPlanService;
use App\Services\MarketCpcDefaultService;
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

    public function handle(
        GoogleAdsKeywordPlanService $keywordPlan,
        MarketCpcDefaultService $marketDefaults,
    ): void {
        ScannerJobContext::add(self::class, ['search_id' => $this->search->id]);

        $search = $this->search->fresh(['user']);

        if (! $search || (! $this->force && $search->cpc_benchmark !== null)) {
            return;
        }

        if (! $keywordPlan->isAvailable() || $search->niche === null || $search->city === null) {
            return;
        }

        $result = $keywordPlan->lookupForSearch($search);

        if ($result === null) {
            Log::info('google_ads.cpc_not_resolved', [
                'search_id' => $search->id,
                'niche' => $search->niche,
                'city' => $search->city,
            ]);

            return;
        }

        $search->update([
            'cpc_benchmark' => $result->benchmark,
            'cpc_source' => $result->source,
            'cpc_keywords' => $result->keywords,
            'cpc_geo_target' => $result->geoTarget,
        ]);

        $marketDefaults->syncFromResult(
            $search->user,
            $search->niche,
            $search->city,
            $search->country ?? 'GB',
            $result,
        );

        Log::info('google_ads.cpc_saved', [
            'search_id' => $search->id,
            'cpc_benchmark' => $result->benchmark,
            'keyword_count' => count($result->keywords),
        ]);
    }
}
