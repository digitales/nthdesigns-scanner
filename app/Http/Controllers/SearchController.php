<?php

namespace App\Http\Controllers;

use App\Jobs\ScrapeProspectsJob;
use App\Models\Search;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Search/Index', [
            'recentSearches' => auth()->user()
                ->searches()
                ->with('prospects')
                ->latest()
                ->take(5)
                ->get()
                ->map(fn ($s) => [
                    'id'          => $s->id,
                    'niche'       => $s->niche,
                    'city'        => $s->city,
                    'status'      => $s->status,
                    'total_found' => $s->total_found,
                    'created_at'  => $s->created_at->diffForHumans(),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'niche'     => 'required|string|max:100',
            'city'      => 'required|string|max:100',
            'country'   => 'required|string|size:2',
            'scan_type' => 'required|in:gbp_only,accessibility_only,combined',
        ]);

        $search = auth()->user()->searches()->create($validated);

        ScrapeProspectsJob::dispatch($search)->onQueue('scraping');

        return redirect()->route('searches.show', $search);
    }

    public function show(Search $search): Response
    {
        $this->authorize('view', $search);

        $prospects = $search->prospects()
            ->with('report')
            ->orderByDesc('combined_score')
            ->get()
            ->map(fn ($p) => [
                'id'             => $p->id,
                'business_name'  => $p->business_name,
                'address'        => $p->address,
                'phone'          => $p->phone,
                'website_url'    => $p->website_url,
                'rating'         => $p->rating,
                'review_count'   => $p->review_count,
                'photo_count'    => $p->photo_count,
                'has_description'=> $p->has_description,
                'hours_complete' => $p->hours_complete,
                'gbp_score'      => $p->gbp_score,
                'gbp_flags'      => $p->gbp_flags ?? [],
                'a11y_score'     => $p->a11y_score,
                'a11y_flags'     => $p->a11y_flags ?? [],
                'performance_score' => $p->performance_score,
                'combined_score' => $p->combined_score,
                'dominant_angle' => $p->dominant_angle,
                'audit_status'   => $p->audit_status,
            ]);

        return Inertia::render('Search/Show', [
            'search' => [
                'id'          => $search->id,
                'niche'       => $search->niche,
                'city'        => $search->city,
                'country'     => $search->country,
                'scan_type'   => $search->scan_type,
                'status'      => $search->status,
                'total_found' => $search->total_found,
                'created_at'  => $search->created_at->toISOString(),
            ],
            'prospects' => $prospects,
        ]);
    }
}
