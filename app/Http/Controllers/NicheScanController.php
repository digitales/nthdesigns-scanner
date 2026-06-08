<?php

namespace App\Http\Controllers;

use App\Actions\DispatchMarketScanRefresh;
use App\Enums\NicheScanStatus;
use App\Models\NicheScan;
use App\Queries\LatestNicheScanQuery;
use App\Services\NicheExclusionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class NicheScanController extends Controller
{
    public function index(Request $request, NicheExclusionService $exclusions): Response
    {
        $sort = $request->string('sort', 'opportunity_score')->toString();
        $sortColumn = $sort === 'result_count' ? 'result_count' : 'opportunity_score';
        $hideIgnored = $request->string('hide_ignored', '1') !== '0';
        $ignoredLabels = collect($exclusions->ignoredLabels());
        $scanDate = now('Europe/London')->toDateString();

        $latestIds = LatestNicheScanQuery::ids();

        $paginator = NicheScan::query()
            ->when($latestIds->isNotEmpty(), fn ($q) => $q->whereIn('id', $latestIds), fn ($q) => $q->whereRaw('0 = 1'))
            ->when($request->filled('city'), fn ($q) => $q->where('city', $request->string('city')))
            ->when($hideIgnored && $ignoredLabels->isNotEmpty(), fn ($q) => $q->whereNotIn('niche', $ignoredLabels))
            ->orderByDesc($sortColumn)
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Niches/Index', [
            'scans' => $paginator->getCollection()->map(fn (NicheScan $s) => $this->mapScan($s, $ignoredLabels, $scanDate))->values(),
            'pagination' => [
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
            'cities' => NicheScan::query()->distinct()->orderBy('city')->pluck('city')->values(),
            'nicheCatalog' => $exclusions->catalog(),
            'ignoredCount' => $ignoredLabels->count(),
            'filters' => [
                'city' => $request->string('city')->toString() ?: null,
                'sort' => $sortColumn,
                'page' => $paginator->currentPage(),
                'hide_ignored' => $hideIgnored,
            ],
        ]);
    }

    public function refresh(Request $request, NicheScan $nicheScan, DispatchMarketScanRefresh $dispatch): RedirectResponse
    {
        $result = $dispatch(
            niche: $nicheScan->niche,
            city: $nicheScan->city,
            country: $nicheScan->country,
            nicheQueryFallback: $nicheScan->niche_query,
            userId: $request->user()->id,
        );

        if ($result->isAlreadyPending()) {
            return back()->with('success', 'Market scan already in progress.');
        }

        if ($result->isRateLimited()) {
            return back()->with('error', "Please wait {$result->rateLimitSeconds} seconds before refreshing this market scan.");
        }

        return back()->with('success', "Market scan queued for {$nicheScan->niche} in {$nicheScan->city}.");
    }

    public function status(NicheScan $nicheScan): JsonResponse
    {
        $niche = $nicheScan->niche;
        $city = $nicheScan->city;
        $scanDate = now('Europe/London')->toDateString();

        $todayRow = NicheScan::query()
            ->where('niche', $niche)
            ->where('city', $city)
            ->whereDate('scan_date', $scanDate)
            ->first();

        $latestComplete = LatestNicheScanQuery::ranked(
            fn ($query) => $query
                ->where('niche', $niche)
                ->where('city', $city)
                ->where('status', NicheScanStatus::Complete),
        )->first();

        if ($todayRow?->status === NicheScanStatus::Pending) {
            return response()->json($this->statusPayload(
                niche: $niche,
                city: $city,
                id: $todayRow->id,
                isPending: true,
                status: NicheScanStatus::Pending,
                statsSource: $latestComplete,
                errorMessage: null,
            ));
        }

        if ($todayRow?->status === NicheScanStatus::Complete) {
            return response()->json($this->statusPayload(
                niche: $niche,
                city: $city,
                id: $todayRow->id,
                isPending: false,
                status: NicheScanStatus::Complete,
                statsSource: $todayRow,
                errorMessage: null,
            ));
        }

        if ($todayRow?->status === NicheScanStatus::Failed) {
            return response()->json($this->statusPayload(
                niche: $niche,
                city: $city,
                id: $todayRow->id,
                isPending: false,
                status: NicheScanStatus::Failed,
                statsSource: $latestComplete,
                errorMessage: $todayRow->error_message,
            ));
        }

        $latest = LatestNicheScanQuery::ranked(
            fn ($query) => $query->where('niche', $niche)->where('city', $city),
        )->first() ?? $nicheScan;

        return response()->json($this->statusPayload(
            niche: $niche,
            city: $city,
            id: $latest->id,
            isPending: false,
            status: $latest->status ?? NicheScanStatus::Complete,
            statsSource: $latest->status === NicheScanStatus::Complete ? $latest : $latestComplete,
            errorMessage: $latest->error_message,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function mapScan(NicheScan $s, Collection $ignoredLabels, string $scanDate): array
    {
        $isPending = NicheScan::query()
            ->where('niche', $s->niche)
            ->where('city', $s->city)
            ->whereDate('scan_date', $scanDate)
            ->where('status', NicheScanStatus::Pending)
            ->exists();

        return [
            'id' => $s->id,
            'niche' => $s->niche,
            'niche_query' => $s->niche_query,
            'city' => $s->city,
            'country' => $s->country,
            'result_count' => $s->result_count,
            'sampled_count' => $s->sampled_count,
            'avg_gbp_score' => $s->avg_gbp_score,
            'pct_no_website' => $s->pct_no_website,
            'pct_low_reviews' => $s->pct_low_reviews,
            'opportunity_score' => $s->opportunity_score,
            'status' => $isPending ? NicheScanStatus::Pending->value : $s->status->value,
            'is_pending' => $isPending,
            'is_ignored' => $ignoredLabels->contains($s->niche),
            'ran_at' => $s->ran_at?->toISOString(),
            'ran_at_human' => $s->ran_at?->diffForHumans() ?? '—',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function statusPayload(
        string $niche,
        string $city,
        int $id,
        bool $isPending,
        NicheScanStatus $status,
        ?NicheScan $statsSource,
        ?string $errorMessage,
    ): array {
        return [
            'niche' => $niche,
            'city' => $city,
            'id' => $id,
            'is_pending' => $isPending,
            'status' => $status->value,
            'result_count' => $statsSource?->result_count,
            'sampled_count' => $statsSource?->sampled_count,
            'avg_gbp_score' => $statsSource?->avg_gbp_score,
            'pct_no_website' => $statsSource?->pct_no_website,
            'pct_low_reviews' => $statsSource?->pct_low_reviews,
            'opportunity_score' => $statsSource?->opportunity_score,
            'ran_at_human' => $statsSource?->ran_at?->diffForHumans() ?? '—',
            'error_message' => $errorMessage,
        ];
    }
}
