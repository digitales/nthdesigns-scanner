<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicReportResource;
use App\Models\ProspectReport;
use App\Services\AgencyBookingService;
use App\Services\ReportBuilderService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PublicReportController extends Controller
{
    public function show(
        Request $request,
        string $token,
        AgencyBookingService $agencyBooking,
        ReportBuilderService $reportBuilder,
    ): Response|HttpResponse {
        $report = ProspectReport::with(['prospect.search', 'booking'])
            ->where('token', $token)
            ->first();

        if (! $report) {
            abort(404);
        }

        if ($report->expires_at && $report->expires_at->isPast()) {
            abort(410, 'This report has expired.');
        }

        $report->increment('view_count');

        if (! $report->viewed_at) {
            $report->update([
                'viewed_at' => now(),
                'viewer_ip' => $request->ip(),
            ]);
        }

        return Inertia::render('Report/Public', [
            'report' => PublicReportResource::format($report, $request, $agencyBooking, $reportBuilder),
        ]);
    }
}
