<?php

namespace App\Http\Controllers;

use App\Models\ProspectReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PublicReportController extends Controller
{
    public function show(Request $request, string $token): Response|HttpResponse
    {
        $report = ProspectReport::with('prospect.search')
            ->where('token', $token)
            ->first();

        if (!$report) {
            abort(404);
        }

        if ($report->expires_at && $report->expires_at->isPast()) {
            abort(410, 'This report has expired.');
        }

        $report->increment('view_count');

        if (!$report->viewed_at) {
            $report->update([
                'viewed_at'  => now(),
                'viewer_ip'  => $request->ip(),
            ]);
        }

        $data = $report->report_data ?? [];

        return Inertia::render('Report/Public', [
            'report' => [
                'business_name'     => $data['prospect']['business_name'] ?? $report->prospect->business_name,
                'niche'             => $data['niche'] ?? $report->prospect->search->niche,
                'city'              => $data['city'] ?? $report->prospect->search->city,
                'prospect'          => $data['prospect'] ?? [],
                'benchmark'         => $data['benchmark'] ?? null,
                'comparison'        => $data['comparison'] ?? [],
                'booking_url'       => $data['booking_url'] ?? config('scanner.report_booking_url'),
                'generated_at'      => $data['generated_at'] ?? $report->created_at->toISOString(),
                'screenshot_paths'  => $report->screenshot_paths ?? [],
            ],
        ]);
    }
}
