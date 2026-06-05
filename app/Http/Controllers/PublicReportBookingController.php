<?php

namespace App\Http\Controllers;

use App\Models\ProspectReport;
use App\Services\AgencyBookingService;
use App\Services\ReportBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicReportBookingController extends Controller
{
    public function slots(string $token, AgencyBookingService $agencyBooking, ReportBookingService $bookings): JsonResponse
    {
        $report = $this->resolveReport($token);

        if (! $agencyBooking->nativeBookingActive()) {
            return response()->json(['slots' => []]);
        }

        return response()->json([
            'slots' => $bookings->slotsForReport($report),
            'booking' => $this->bookingPayload($report),
        ]);
    }

    public function store(Request $request, string $token, ReportBookingService $bookings): JsonResponse
    {
        $report = $this->resolveReport($token);

        $validated = $request->validate([
            'starts_at' => 'required|date',
            'attendee_name' => 'required|string|max:120',
            'attendee_email' => 'required|email|max:255',
            'attendee_phone' => 'nullable|string|max:40',
            'note' => 'nullable|string|max:500',
        ]);

        $booking = $bookings->book($report, $validated);

        return response()->json([
            'booking' => $this->bookingPayload($report->fresh(['booking'])),
        ], 201);
    }

    private function resolveReport(string $token): ProspectReport
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

        return $report;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function bookingPayload(ProspectReport $report): ?array
    {
        $booking = $report->booking;

        if (! $booking) {
            return null;
        }

        $settings = app(AgencyBookingService::class)->settings();

        return [
            'starts_at' => $booking->starts_at->toIso8601String(),
            'ends_at' => $booking->ends_at->toIso8601String(),
            'label' => $booking->starts_at->timezone($settings->timezone)->format('l j F Y, g:i A'),
            'attendee_name' => $booking->attendee_name,
        ];
    }
}
