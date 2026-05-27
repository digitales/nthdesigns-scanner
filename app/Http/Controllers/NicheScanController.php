<?php

namespace App\Http\Controllers;

use App\Models\NicheScan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

class NicheScanController extends Controller
{
    public function index(Request $request): Response
    {
        $sort = $request->string('sort', 'opportunity_score')->toString();
        $sortColumn = $sort === 'result_count' ? 'result_count' : 'opportunity_score';

        $latestIds = NicheScan::query()
            ->fromSub(
                NicheScan::query()
                    ->select('*')
                    ->selectRaw('ROW_NUMBER() OVER (PARTITION BY niche, city ORDER BY ran_at DESC, id DESC) AS row_num'),
                'ranked',
            )
            ->where('row_num', 1)
            ->pluck('id');

        $scans = NicheScan::query()
            ->when($latestIds->isNotEmpty(), fn ($q) => $q->whereIn('id', $latestIds), fn ($q) => $q->whereRaw('0 = 1'))
            ->when($request->filled('city'), fn ($q) => $q->where('city', $request->string('city')))
            ->orderByDesc($sortColumn)
            ->get()
            ->map(fn (NicheScan $s) => [
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
                'status' => $s->status,
                'ran_at' => $s->ran_at?->toISOString(),
                'ran_at_human' => $s->ran_at?->diffForHumans() ?? '—',
            ]);

        return Inertia::render('Niches/Index', [
            'scans' => $scans,
            'cities' => NicheScan::query()->distinct()->orderBy('city')->pluck('city')->values(),
            'filters' => [
                'city' => $request->string('city')->toString() ?: null,
                'sort' => $sortColumn,
            ],
        ]);
    }

    public function trigger(): RedirectResponse
    {
        Artisan::queue('niches:scan');

        return back()->with('success', 'Scan queued');
    }
}
