<?php

namespace App\Services\Booking;

use App\Mail\ReportBookingConfirmed;
use App\Models\ReportBooking;
use App\Services\AgencyBookingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReportBookingConfirmationSender
{
    public function __construct(
        private AgencyBookingService $agencyBooking,
    ) {}

    public function send(ReportBooking $booking): bool
    {
        $booking->loadMissing('report.prospect.search');
        $report = $booking->report;

        if (! $report) {
            Log::warning('ReportBookingConfirmed skipped — missing report', [
                'booking_id' => $booking->id,
            ]);

            return false;
        }

        $settings = $this->agencyBooking->settings();
        $businessName = $report->report_data['prospect']['business_name'] ?? $report->prospect->business_name;
        $reportUrl = url('/r/'.$report->token);

        try {
            Mail::to($booking->attendee_email)->send(new ReportBookingConfirmed(
                booking: $booking,
                businessName: $businessName,
                reportUrl: $reportUrl,
                settings: $settings,
            ));

            $booking->update(['confirmation_sent_at' => now()]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('ReportBookingConfirmed mail failed', [
                'booking_id' => $booking->id,
                'report_id' => $report->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
