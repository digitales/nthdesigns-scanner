<?php

namespace App\Http\Controllers;

use App\Enums\ScanType;
use App\Enums\SearchSource;
use App\Enums\SearchStatus;
use App\Http\Requests\StoreDirectUrlSearchRequest;
use App\Http\Requests\StoreSearchRequest;
use App\Http\Resources\SearchProspectResource;
use App\Http\Resources\SearchSummaryMapper;
use App\Jobs\DirectUrlScanJob;
use App\Jobs\ScrapeProspectsJob;
use App\Models\Search;
use App\Services\ProgressFlowService;
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
    public function __construct(private UserSettingsService $settings) {}

    public function index(): Response
    {
        $user = auth()->user();

        return Inertia::render('Search/Index', [
            'defaults' => [
                'country' => $this->settings->defaultCountry($user),
            ],
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

        $search = $user->searches()->create($validated);

        ScrapeProspectsJob::dispatch($search);

        return redirect()->route('searches.show', $search);
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

    public function show(
        Search $search,
        ReportBuilderService $reportBuilder,
        ProgressFlowService $progressFlow,
    ): Response {
        $this->authorize('view', $search);

        $prospects = $search->prospects()
            ->with([
                'report',
                'outreachEmails' => fn ($q) => $q->latest()->limit(1),
                'auditJobs' => fn ($q) => $q->latest()->limit(1),
            ])
            ->orderByDesc('combined_score')
            ->get();

        $searchFlow = $progressFlow->searchFlow($search, $prospects);

        return Inertia::render('Search/Show', [
            'outreachProspectIds' => auth()->user()
                ->outreachSelections()
                ->pluck('prospect_id')
                ->values(),
            'search' => SearchSummaryMapper::forShow($search, $searchFlow),
            'prospects' => $prospects->map(fn ($prospect) => SearchProspectResource::format(
                $prospect,
                $search,
                $reportBuilder,
                $progressFlow,
            )),
        ]);
    }
}
