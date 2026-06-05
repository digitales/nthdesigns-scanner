<?php

namespace App\Http\Controllers;

use App\Models\ProspectReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['niche', 'viewed', 'warm']);

        $query = ProspectReport::query()
            ->whereHas('prospect.search', fn (Builder $q) => $q->where('user_id', $request->user()->id))
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

        $allReports = ProspectReport::query()
            ->whereHas('prospect.search', fn (Builder $q) => $q->where('user_id', $request->user()->id));

        $stats = [
            'total_reports' => (clone $allReports)->count(),
            'total_views' => (clone $allReports)->sum('view_count'),
            'warm_7d' => (clone $allReports)->where('viewed_at', '>=', now()->subDays(7))->count(),
            'avg_views' => round((clone $allReports)->avg('view_count') ?? 0, 1),
        ];

        $reports = $query
            ->orderByDesc('viewed_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (ProspectReport $report) {
                $latest = $report->prospect->outreachEmails->first();
                $data = $report->report_data ?? [];

                return [
                    'id' => $report->id,
                    'prospect_id' => $report->prospect_id,
                    'business_name' => $data['prospect']['business_name'] ?? $report->prospect->business_name,
                    'niche' => $data['niche'] ?? $report->prospect->search->niche,
                    'city' => $data['city'] ?? $report->prospect->search->city,
                    'token' => $report->token,
                    'public_url' => url('/r/'.$report->token),
                    'view_count' => $report->view_count,
                    'viewed_at' => $report->viewed_at?->toISOString(),
                    'viewer_ip' => $report->viewer_ip,
                    'created_at' => $report->created_at->diffForHumans(),
                    'is_engaged_badge' => $report->viewed_at?->gte(now()->subDays(7)) ?? false,
                    'has_outreach_sent' => $latest?->sent_at !== null,
                    'response_received' => (bool) ($latest?->response_received ?? false),
                ];
            });

        return Inertia::render('Reports/Index', [
            'reports' => $reports,
            'filters' => $filters,
            'stats' => $stats,
        ]);
    }
}
