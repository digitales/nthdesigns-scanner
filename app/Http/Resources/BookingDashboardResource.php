<?php

namespace App\Http\Resources;

use App\Models\ReportBooking;
use App\Services\AgencyBookingService;
use App\Services\Booking\BookingPresentation;

class BookingDashboardResource
{
    /**
     * @return array<string, mixed>
     */
    public static function format(ReportBooking $booking): array
    {
        $settings = app(AgencyBookingService::class)->settings();
        $report = $booking->report;
        $prospect = $booking->prospect;

        return [
            'id' => $booking->id,
            'prospect_id' => $booking->prospect_id,
            'business_name' => $prospect?->business_name,
            'niche' => $prospect?->search?->niche,
            'city' => $prospect?->search?->city,
            'label' => $booking->starts_at->timezone($settings->timezone)->format('l j M Y, g:ia'),
            'attendee_name' => $booking->attendee_name,
            'attendee_email' => $booking->attendee_email,
            'attendee_phone' => $booking->attendee_phone,
            'note' => $booking->note,
            'confirmation_sent' => $booking->confirmation_sent_at !== null,
            'report_url' => $report ? url('/r/'.$report->token.'#book') : null,
            'prospect_url' => $prospect ? url('/prospects/'.$prospect->id) : null,
            'can_resend_confirmation' => $booking->confirmation_sent_at === null,
            'timezone_label' => BookingPresentation::timezoneLabel($settings->timezone),
        ];
    }
}
