<?php

namespace App\Http\Controllers;

use App\Enums\ScanType;
use App\Enums\SearchSource;
use App\Enums\SearchStatus;
use App\Http\Requests\ImportSearchCpcRequest;
use App\Http\Requests\StoreDirectUrlSearchRequest;
use App\Http\Requests\StoreSearchRequest;
use App\Http\Requests\UpdateSearchCpcRequest;
use App\Http\Resources\SearchProspectResource;
use App\Http\Resources\SearchSummaryMapper;
use App\Jobs\DirectUrlScanJob;
use App\Jobs\FetchSearchCpcJob;
use App\Jobs\ScrapeProspectsJob;
use App\Models\Search;
use App\Models\User;
use App\Services\GoogleAds\GoogleAdsKeywordPlanService;
use App\Services\KeywordPlanner\KeywordPlannerCsvImporter;
use App\Services\KeywordPlanner\KeywordPlannerImportException;
use App\Services\MarketCpcDefaultService;
use App\Services\ProgressFlowService;
use App\Services\ProspectListMembershipService;
use App\Services\ReportBuilderService;
use App\Services\UserSettingsService;
use App\Support\WebsiteUrlNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __construct(
        private UserSettingsService $settings,
        private MarketCpcDefaultService $marketCpcDefaults,
        private GoogleAdsKeywordPlanService $googleAdsKeywordPlan,
    ) {}

    public function index(): Response
    {
        $user = auth()->user();

        return Inertia::render('Search/Index', [
            'defaults' => [
                'country' => $this->settings->defaultCountry($user),
            ],
            'googleAdsCpcAvailable' => $this->googleAdsKeywordPlan->isAvailable(),
            'recentSearches' => $user
                ->searches()
                ->latest()
                ->take(4)
                ->get()
                ->map(fn ($s) => SearchSummaryMapper::format($s)),
        ]);
    }

    public function history(): Response
    {
        $paginator = auth()->user()
            ->searches()
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Search/History', [
            'searches' => $paginator->getCollection()
                ->map(fn ($s) => SearchSummaryMapper::format($s))
                ->values(),
            'pagination' => [
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(StoreSearchRequest $request): RedirectResponse
    {
        $user = $request->user();
        $rateKey = 'search-submit:'.$user->id;
        $decay = config('scanner.search_rate_limit_seconds', 30);

        if (RateLimiter::tooManyAttempts($rateKey, 1)) {
            $seconds = RateLimiter::availableIn($rateKey);

            throw ValidationException::withMessages([
                'niche' => "Please wait {$seconds} seconds before starting another search.",
            ]);
        }

        $validated = $request->validated();

        RateLimiter::hit($rateKey, $decay);

        $search = $user->searches()->create([
            ...collect($validated)->only(['niche', 'city', 'country', 'scan_type'])->all(),
            'cpc_benchmark' => $validated['cpc_benchmark'] ?? null,
            'cpc_source' => isset($validated['cpc_benchmark']) ? 'manual' : null,
        ]);

        if (! isset($validated['cpc_benchmark'])) {
            $this->marketCpcDefaults->applyFromDefault($search, $user);
        }

        ScrapeProspectsJob::dispatch($search);

        if ($this->shouldAutoFetchCpc($validated)) {
            FetchSearchCpcJob::dispatch($search);
        }

        return redirect()->route('searches.show', $search);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function shouldAutoFetchCpc(array $validated): bool
    {
        if (! config('google_ads.auto_fetch_on_search') || ! config('google_ads.enabled')) {
            return false;
        }

        return ! isset($validated['cpc_benchmark']) || $validated['cpc_benchmark'] === null || $validated['cpc_benchmark'] === '';
    }

    public function storeDirectUrl(StoreDirectUrlSearchRequest $request, WebsiteUrlNormalizer $normalizer): RedirectResponse
    {
        $user = $request->user();
        $rateKey = 'search-submit:'.$user->id;
        $decay = config('scanner.search_rate_limit_seconds', 30);

        if (RateLimiter::tooManyAttempts($rateKey, 1)) {
            $seconds = RateLimiter::availableIn($rateKey);

            throw ValidationException::withMessages([
                'website_url' => "Please wait {$seconds} seconds before starting another search.",
            ]);
        }

        $url = $normalizer->normalize($request->validated('website_url'));

        RateLimiter::hit($rateKey, $decay);

        $search = $user->searches()->create([
            'source' => SearchSource::DirectUrl,
            'submitted_url' => $url,
            'country' => $this->settings->defaultCountry($user),
            'scan_type' => ScanType::Combined,
            'status' => SearchStatus::Pending,
            'total_found' => 1,
        ]);

        DirectUrlScanJob::dispatch($search);

        return redirect()->route('searches.show', $search);
    }

    public function updateCpc(UpdateSearchCpcRequest $request, Search $search): RedirectResponse
    {
        $this->authorize('update', $search);

        $validated = $request->validated();
        $cpcBenchmark = $validated['cpc_benchmark'] ?? null;
        $keywords = $request->normalizedKeywords();
        $saveMarketDefault = $request->boolean('save_market_default', true);

        $this->persistCpc(
            $search,
            $request->user(),
            $cpcBenchmark !== null ? (float) $cpcBenchmark : null,
            $keywords,
            $validated['cpc_source'] ?? 'manual',
            $saveMarketDefault,
        );

        return back()->with('success', $cpcBenchmark !== null
            ? 'CPC benchmark saved for this search and as the default for this niche and city.'
            : 'CPC benchmark cleared.');
    }

    public function importCpc(
        ImportSearchCpcRequest $request,
        Search $search,
        KeywordPlannerCsvImporter $importer,
    ): RedirectResponse {
        $this->authorize('update', $search);

        if ($search->niche === null || $search->city === null) {
            return back()->with('error', 'CPC import requires a niche and city search.');
        }

        try {
            $result = $importer->import($request->file('file'));
        } catch (KeywordPlannerImportException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->persistCpc(
            $search,
            $request->user(),
            $result->benchmark,
            $result->keywords,
            'keyword_planner_csv',
        );

        return back()->with('success', sprintf(
            'Imported %d keywords · CPC £%s (median of %d commercial terms)',
            $result->totalCount,
            number_format($result->benchmark, 2),
            $result->commercialCount,
        ));
    }

    public function fetchCpc(Search $search): RedirectResponse
    {
        $this->authorize('update', $search);

        if ($search->niche === null || $search->city === null) {
            return back()->with('error', 'CPC lookup requires a niche and city search.');
        }

        if (! $this->googleAdsKeywordPlan->isAvailable()) {
            return back()->with('error', 'Google Ads CPC lookup is not configured.');
        }

        FetchSearchCpcJob::dispatch($search, force: true);

        return back()->with('success', 'CPC lookup queued from Google Ads. Refresh in a few seconds.');
    }

    /**
     * @param  list<string>  $keywords
     */
    private function persistCpc(
        Search $search,
        User $user,
        ?float $cpcBenchmark,
        array $keywords,
        string $cpcSource,
        bool $saveMarketDefault = true,
    ): void {
        $search->update([
            'cpc_benchmark' => $cpcBenchmark,
            'cpc_source' => $cpcBenchmark !== null ? $cpcSource : null,
            'cpc_keywords' => $cpcBenchmark !== null ? $keywords : null,
        ]);

        if ($saveMarketDefault && $search->niche && $search->city && $cpcBenchmark !== null) {
            $this->marketCpcDefaults->upsert(
                $user,
                $search->niche,
                $search->city,
                $search->country ?? 'GB',
                [
                    'cpc_benchmark' => $cpcBenchmark,
                    'cpc_source' => $cpcSource,
                    'cpc_keywords' => $keywords,
                    'cpc_geo_target' => $search->cpc_geo_target,
                ],
            );
        }
    }

    public function show(
        Search $search,
        ReportBuilderService $reportBuilder,
        ProgressFlowService $progressFlow,
        ProspectListMembershipService $listMembership,
    ): Response {
        $this->authorize('view', $search);

        $user = auth()->user();

        $prospects = $search->prospects()
            ->with([
                'report',
                'outreachEmails' => fn ($q) => $q->latest()->limit(1),
                'auditJobs' => fn ($q) => $q->latest()->limit(1),
            ])
            ->orderByDesc('combined_score')
            ->get();

        $searchFlow = $progressFlow->searchFlow($search, $prospects);
        $membershipsByProspectId = $listMembership->membershipsByProspectId(
            $user,
            $prospects->pluck('id'),
        );

        return Inertia::render('Search/Show', [
            'outreachProspectIds' => $user
                ->outreachSelections()
                ->pluck('prospect_id')
                ->values(),
            'manualLists' => $listMembership->manualListsFor($user),
            'search' => SearchSummaryMapper::forShow($search, $searchFlow),
            'marketCpcDefault' => $this->marketCpcDefaults->format(
                $search->niche && $search->city
                    ? $this->marketCpcDefaults->find($user, $search->niche, $search->city, $search->country ?? 'GB')
                    : null,
            ),
            'googleAdsCpcAvailable' => $this->googleAdsKeywordPlan->isAvailable(),
            'prospects' => $prospects->map(function ($prospect) use (
                $search,
                $reportBuilder,
                $progressFlow,
                $membershipsByProspectId,
            ) {
                return [
                    ...SearchProspectResource::format(
                        $prospect,
                        $search,
                        $reportBuilder,
                        $progressFlow,
                    ),
                    'list_memberships' => $membershipsByProspectId[$prospect->id] ?? [],
                ];
            }),
        ]);
    }
}
