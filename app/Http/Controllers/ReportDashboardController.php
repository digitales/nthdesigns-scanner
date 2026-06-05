<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterReportDashboardRequest;
use App\Http\Resources\ReportDashboardResource;
use App\Models\ProspectReport;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class ReportDashboardController extends Controller
{
    public function index(FilterReportDashboardRequest $request): Response
    {
        $filters = $request->validated();

        $baseQuery = ProspectReport::query()
            ->whereHas('prospect.search', fn (Builder $q) => $q->where('user_id', $request->user()->id));

        $statsRow = (clone $baseQuery)->toBase()
            ->selectRaw('count(*) as total_reports')
            ->selectRaw('coalesce(sum(view_count), 0) as total_views')
            ->selectRaw('sum(case when viewed_at >= ? then 1 else 0 end) as warm_7d', [now()->subDays(7)])
            ->selectRaw('coalesce(avg(view_count), 0) as avg_views')
            ->first();

        $stats = [
            'total_reports' => (int) ($statsRow->total_reports ?? 0),
            'total_views' => (int) ($statsRow->total_views ?? 0),
            'warm_7d' => (int) ($statsRow->warm_7d ?? 0),
            'avg_views' => round((float) ($statsRow->avg_views ?? 0), 1),
        ];

        $query = (clone $baseQuery)
            ->with(['prospect.search', 'prospect.outreachEmails' => fn ($q) => $q->latest()->limit(1)]);

        if (! empty($filters['niche'])) {
            $query->whereHas('prospect.search', fn (Builder $q) => $q
                ->where('niche', 'like', '%'.$filters['niche'].'%'));
        }

        if (isset($filters['viewed']) && $filters['viewed'] !== '') {
            if ($filters['viewed'] === '1') {
                $query->whereNotNull('viewed_at');
            } elseif ($filters['viewed'] === '0') {
                $query->whereNull('viewed_at');
            }
        }

        if (! empty($filters['warm'])) {
            $query->where('viewed_at', '>=', now()->subDays(7));
        }

        $paginator = $query
            ->orderByDesc('viewed_at')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $reports = collect($paginator->items())
            ->map(fn (ProspectReport $report) => ReportDashboardResource::format($report))
            ->values();

        return Inertia::render('Reports/Index', [
            'reports' => $reports,
            'filters' => $filters,
            'stats' => $stats,
            'pagination' => [
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
