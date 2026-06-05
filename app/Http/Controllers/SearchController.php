<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDirectUrlSearchRequest;
use App\Http\Requests\StoreSearchRequest;
use App\Jobs\DirectUrlScanJob;
use App\Jobs\ScrapeProspectsJob;
use App\Models\Search;
use App\Services\ProgressFlowService;
use App\Services\ReportBuilderService;
use App\Services\UserSettingsService;
use App\Support\WebsiteUrlNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                ->map(fn ($s) => $this->mapSearchSummary($s)),
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
                ->map(fn ($s) => $this->mapSearchSummary($s))
                ->values(),
            'pagination' => [
                'total'        => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'last_page'    => $paginator->lastPage(),
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
            'source'        => 'direct_url',
            'submitted_url' => $url,
            'country'       => $this->settings->defaultCountry($user),
            'scan_type'     => 'combined',
            'status'        => 'pending',
            'total_found'   => 1,
        ]);

        DirectUrlScanJob::dispatch($search);

        return redirect()->route('searches.show', $search);
    }

    public function show(
        Search $search,
        ReportBuilderService $reportBuilder,
        ProgressFlowService $progressFlow,
    ): Response
    {
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

        $prospectPayloads = $prospects->map(function ($p) use ($reportBuilder, $progressFlow, $search) {
                $latestOutreach = $p->outreachEmails->first();
                $isWarm = $p->report?->viewed_at !== null
                    && $latestOutreach?->sent_at !== null
                    && !($latestOutreach?->response_received ?? false);
                $cms = $reportBuilder->cmsForProspect($p);
                $failedAudit = $p->auditJobs->firstWhere('status', 'failed') ?? $p->auditJobs->first();

                return [
                    'id'                => $p->id,
                    'business_name'     => $p->business_name,
                    'address'           => $p->address,
                    'phone'             => $p->phone,
                    'website_url'       => $p->website_url,
                    'place_id'          => $p->place_id,
                    'rating'            => $p->rating,
                    'review_count'      => $p->review_count,
                    'photo_count'       => $p->photo_count,
                    'has_description'   => $p->has_description,
                    'hours_complete'    => $p->hours_complete,
                    'gbp_score'         => $p->gbp_score,
                    'gbp_flags'         => $p->gbp_flags ?? [],
                    'a11y_score'        => $p->a11y_score,
                    'a11y_flags'        => $p->a11y_flags ?? [],
                    'performance_score' => $p->performance_score,
                    'combined_score'    => $p->combined_score,
                    'dominant_angle'    => $p->dominant_angle,
                    'audit_status'      => $p->audit_status,
                    'audit_error'       => $failedAudit?->error_message,
                    'report_ready'      => $p->report !== null,
                    'report_url'        => $p->report ? url('/r/'.$p->report->token) : null,
                    'is_warm'           => $isWarm,
                    'last_viewed'       => $p->report?->viewed_at?->diffForHumans(),
                    'cms_badge'         => $cms['badge'] ?? null,
                    'cms_pending'       => $cms['pending'] ?? false,
                    'progress_flow'     => $progressFlow->prospectFlow($p, $search),
                ];
            });

        return Inertia::render('Search/Show', [
            'outreachProspectIds' => auth()->user()
                ->outreachSelections()
                ->pluck('prospect_id')
                ->values(),
            'search' => [
                'id'             => $search->id,
                'source'         => $search->source,
                'submitted_url'  => $search->submitted_url,
                'niche'          => $search->niche,
                'city'           => $search->city,
                'country'        => $search->country,
                'scan_type'      => $search->scan_type,
                'status'         => $search->status,
                'total_found'    => $search->total_found,
                'created_at'     => $search->created_at->toISOString(),
                'progress_flow'  => $searchFlow,
            ],
            'prospects' => $prospectPayloads,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapSearchSummary(Search $search): array
    {
        return [
            'id'            => $search->id,
            'source'        => $search->source,
            'submitted_url' => $search->submitted_url,
            'niche'         => $search->niche,
            'city'          => $search->city,
            'status'        => $search->status,
            'total_found'   => $search->total_found,
            'created_at'    => $search->created_at->diffForHumans(),
        ];
    }
}
