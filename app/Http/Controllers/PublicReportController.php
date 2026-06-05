<?php

namespace App\Http\Controllers;

use App\Models\ProspectReport;
use App\Services\AgencyBookingService;
use App\Support\TidyCalEmbed;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PublicReportController extends Controller
{
    public function show(Request $request, string $token, AgencyBookingService $agencyBooking): Response|HttpResponse
    {
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

        $data = $report->report_data ?? [];
        $nativeBooking = $agencyBooking->nativeBookingActive();
        $bookingUrl = $data['booking_url'] ?? config('scanner.report_booking_url');
        $bookCtaUrl = $nativeBooking
            ? null
            : (TidyCalEmbed::bookPageUrl($bookingUrl, ['report' => $report->token]) ?? $bookingUrl);
        $booking = $report->booking;

        return Inertia::render('Report/Public', [
            'report' => [
                'business_name' => $data['prospect']['business_name'] ?? $report->prospect->business_name,
                'address' => $data['prospect']['address'] ?? $report->prospect->address,
                'niche' => $data['niche'] ?? $report->prospect->search->niche,
                'city' => $data['city'] ?? $report->prospect->search->city,
                'website_url' => $data['website_url'] ?? $report->prospect->website_url,
                'prospect' => $data['prospect'] ?? [],
                'benchmark' => $data['benchmark'] ?? null,
                'comparison' => $data['comparison'] ?? [],
                'grade' => $data['grade'] ?? 'C',
                'grade_label' => $data['grade_label'] ?? 'Review recommended',
                'combined_score' => $report->prospect->combined_score,
                'performance_score' => $report->prospect->performance_score,
                'violation_summary' => $data['violation_summary'] ?? ['critical' => 0, 'serious' => 0, 'moderate' => 0, 'minor' => 0, 'total' => 0],
                'top_violations' => $data['top_violations'] ?? [],
                'lighthouse' => $data['lighthouse'] ?? [],
                'booking_url' => $nativeBooking ? null : $bookingUrl,
                'book_cta_url' => $bookCtaUrl,
                'book_cta_external' => $bookingUrl && ! TidyCalEmbed::isEmbeddable($bookingUrl),
                'native_booking' => $nativeBooking,
                'booking' => $booking ? [
                    'starts_at' => $booking->starts_at->toIso8601String(),
                    'label' => $booking->starts_at
                        ->timezone($agencyBooking->settings()->timezone)
                        ->format('l j F Y, g:i A'),
                    'attendee_name' => $booking->attendee_name,
                ] : null,
                'generated_at' => $data['generated_at'] ?? $report->created_at->toISOString(),
                'expires_at' => $report->expires_at?->toISOString(),
                'token' => $report->token,
                'screenshot_paths' => $report->screenshot_paths ?? [],
            ],
        ]);
    }
}
