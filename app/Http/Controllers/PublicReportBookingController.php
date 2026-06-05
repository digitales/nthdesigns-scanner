<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublicReportBookingRequest;
use App\Models\ProspectReport;
use App\Services\AgencyBookingService;
use App\Services\Booking\BookingPresentation;
use App\Services\ReportBookingService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PublicReportBookingController extends Controller
{
    public function slots(string $token, AgencyBookingService $agencyBooking, ReportBookingService $bookings): JsonResponse
    {
        $report = $this->resolveReport($token);

        if (! $agencyBooking->nativeBookingActive()) {
            return response()->json(['slots' => []]);
        }

        $settings = $agencyBooking->settings();

        try {
            $slots = $bookings->slotsForReport($report);
        } catch (RequestException) {
            return response()->json([
                'message' => 'Booking is temporarily unavailable. Please try again shortly.',
                'slots' => [],
            ], 503);
        }

        return response()->json([
            'slots' => $slots,
            'booking' => $this->bookingPayload($report, $agencyBooking),
            'timezone_label' => BookingPresentation::timezoneLabel($settings->timezone),
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
            'booking' => $this->bookingPayload($report->fresh(['booking']), app(AgencyBookingService::class)),
        ], 201);
    }

    public function ics(string $token, AgencyBookingService $agencyBooking): Response
    {
        $report = $this->resolveReport($token);
        $booking = $report->booking;

        if (! $booking) {
            abort(404);
        }

        $ics = BookingPresentation::icsForBooking($booking, $report, $agencyBooking->settings());

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="review-call.ics"',
        ]);
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
    private function bookingPayload(ProspectReport $report, AgencyBookingService $agencyBooking): ?array
    {
        $booking = $report->booking;

        if (! $booking) {
            return null;
        }

        return BookingPresentation::publicBookingPayload(
            $booking,
            $agencyBooking->settings(),
            $report->token,
        );
    }
}
