<?php

namespace App\Services\Booking;

use App\Contracts\Calendar\CalendarEventDraft;
use App\Models\AgencyBookingSetting;
use App\Models\ProspectReport;
use App\Models\ReportBooking;
use App\Services\Calendar\IcsEventBuilder;
use Carbon\Carbon;

final class BookingPresentation
{
    private const TIMEZONE_LABELS = [
        'Europe/London' => 'UK (London)',
        'Europe/Dublin' => 'Ireland (Dublin)',
        'America/New_York' => 'US Eastern',
        'America/Chicago' => 'US Central',
        'America/Denver' => 'US Mountain',
        'America/Los_Angeles' => 'US Pacific',
    ];

    public static function timezoneLabel(?string $timezone): string
    {
        $timezone = $timezone ?: config('booking.default_timezone');

        return self::TIMEZONE_LABELS[$timezone] ?? str_replace('_', ' ', $timezone);
    }

    /**
     * @return array<string, mixed>
     */
    public static function publicBookingPayload(ReportBooking $booking, AgencyBookingSetting $settings, string $reportToken): array
    {
        $localStart = $booking->starts_at->timezone($settings->timezone);

        return [
            'starts_at' => $booking->starts_at->toIso8601String(),
            'ends_at' => $booking->ends_at->toIso8601String(),
            'label' => $localStart->format('l j F Y, g:i A'),
            'attendee_name' => $booking->attendee_name,
            'confirmation_sent' => $booking->confirmation_sent_at !== null,
            'timezone_label' => self::timezoneLabel($settings->timezone),
            'ics_url' => url('/r/'.$reportToken.'/booking.ics'),
            'google_calendar_url' => self::googleCalendarUrl($booking, $settings, $reportToken),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function operatorBookingPayload(ReportBooking $booking, AgencyBookingSetting $settings): array
    {
        $localStart = $booking->starts_at->timezone($settings->timezone);

        return [
            'label' => $localStart->format('l j M Y, g:ia'),
            'attendee_name' => $booking->attendee_name,
            'attendee_email' => $booking->attendee_email,
            'attendee_phone' => $booking->attendee_phone,
            'note' => $booking->note,
            'confirmation_sent' => $booking->confirmation_sent_at !== null,
            'confirmation_sent_at' => $booking->confirmation_sent_at?->toIso8601String(),
            'can_resend_confirmation' => $booking->confirmation_sent_at === null,
        ];
    }

    public static function googleCalendarUrl(ReportBooking $booking, AgencyBookingSetting $settings, string $reportToken): string
    {
        $reportUrl = url('/r/'.$reportToken);
        $businessName = $booking->prospect?->business_name ?? 'Review call';
        $text = 'Review call — '.$businessName;
        $details = implode("\n", array_filter([
            'Audit report: '.$reportUrl,
            filled($booking->note) ? 'Note: '.$booking->note : null,
        ]));
        $start = $booking->starts_at->copy()->utc()->format('Ymd\THis\Z');
        $end = $booking->ends_at->copy()->utc()->format('Ymd\THis\Z');

        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => $text,
            'dates' => $start.'/'.$end,
            'details' => $details,
        ]);
    }

    public static function icsForBooking(
        ReportBooking $booking,
        ProspectReport $report,
        AgencyBookingSetting $settings,
    ): string {
        $businessName = $report->report_data['prospect']['business_name'] ?? $report->prospect->business_name;
        $reportUrl = url('/r/'.$report->token);

        $draft = new CalendarEventDraft(
            startsAt: Carbon::parse($booking->starts_at),
            endsAt: Carbon::parse($booking->ends_at),
            summary: 'Review call — '.$businessName,
            description: implode("\n", array_filter([
                'Audit report: '.$reportUrl,
                'Prospect #'.$report->prospect_id,
                filled($booking->note) ? 'Note: '.$booking->note : null,
            ])),
            attendeeEmail: $booking->attendee_email,
            attendeeName: $booking->attendee_name,
            uid: $booking->calendar_event_uid,
        );

        return (new IcsEventBuilder)->build($draft, $settings->fastmail_username);
    }
}
