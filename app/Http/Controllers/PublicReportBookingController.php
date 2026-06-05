<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublicReportBookingRequest;
use App\Models\ProspectReport;
use App\Services\AgencyBookingService;
use App\Services\ReportBookingService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;

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

    public function store(StorePublicReportBookingRequest $request, string $token, ReportBookingService $bookings): JsonResponse
    {
        $report = $this->resolveReport($token);

        try {
            $bookings->book($report, $request->validated());
        } catch (RequestException) {
            return response()->json([
                'message' => 'Booking is temporarily unavailable. Please try again shortly.',
            ], 503);
        }

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
