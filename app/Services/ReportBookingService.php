<?php

namespace App\Services;

use App\Contracts\Calendar\CalendarEventDraft;
use App\Contracts\Calendar\CalendarProvider;
use App\Jobs\SendReportBookingConfirmationJob;
use App\Models\ProspectReport;
use App\Models\ReportBooking;
use App\Services\Calendar\BookingAvailabilityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReportBookingService
{
    public function __construct(
        private AgencyBookingService $agencyBooking,
        private BookingAvailabilityService $availability,
        private CalendarProvider $calendar,
    ) {}

    /**
     * @return list<array{starts_at: string, ends_at: string, label: string}>
     */
    public function slotsForReport(ProspectReport $report): array
    {
        $settings = $this->agencyBooking->settings();

        if (! $settings->nativeBookingActive()) {
            return [];
        }

        return $this->availability->availableSlots($settings);
    }

    /**
     * @param  array{starts_at: string, attendee_name: string, attendee_email: string, attendee_phone?: string|null, note?: string|null}  $input
     */
    public function book(ProspectReport $report, array $input): ReportBooking
    {
        if ($report->booking) {
            abort(409, 'This report already has a booking.');
        }

        $settings = $this->agencyBooking->settings();

        if (! $settings->nativeBookingActive()) {
            abort(503, 'Online booking is not available.');
        }

        $startsAt = Carbon::parse($input['starts_at']);
        $endsAt = $startsAt->copy()->addMinutes($settings->event_duration_minutes);

        if (! $this->availability->slotIsAvailable($settings, $startsAt)) {
            abort(409, 'That time is no longer available. Please choose another slot.');
        }

        $report->loadMissing('prospect.search');
        $businessName = $report->report_data['prospect']['business_name'] ?? $report->prospect->business_name;
        $uid = 'scanner-'.Str::uuid().'@nthdesigns.co.uk';

        $draft = new CalendarEventDraft(
            startsAt: $startsAt,
            endsAt: $endsAt,
            summary: 'Review call — '.$businessName,
            description: implode("\n", array_filter([
                'Audit report: '.url('/r/'.$report->token),
                'Prospect #'.$report->prospect_id,
                filled($input['note'] ?? null) ? 'Note: '.$input['note'] : null,
            ])),
            attendeeEmail: $input['attendee_email'],
            attendeeName: $input['attendee_name'],
            uid: $uid,
        );

        $this->calendar->createEvent($draft);

        try {
            $booking = DB::transaction(fn () => ReportBooking::create([
                'prospect_report_id' => $report->id,
                'prospect_id' => $report->prospect_id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'attendee_name' => $input['attendee_name'],
                'attendee_email' => $input['attendee_email'],
                'attendee_phone' => $input['attendee_phone'] ?? null,
                'note' => $input['note'] ?? null,
                'calendar_event_uid' => $uid,
                'status' => 'confirmed',
            ]));
        } catch (\Throwable $e) {
            $this->rollbackCalendarEvent($uid, $report->id);

            throw $e;
        }

        SendReportBookingConfirmationJob::dispatch($booking->id);

        return $booking->fresh();
    }

    public function queueConfirmation(ReportBooking $booking): void
    {
        SendReportBookingConfirmationJob::dispatch($booking->id);
    }

    private function rollbackCalendarEvent(string $uid, int $reportId): void
    {
        try {
            $this->calendar->deleteEvent($uid);
        } catch (\Throwable $cleanup) {
            Log::warning('Failed to roll back calendar event after booking persistence failed', [
                'report_id' => $reportId,
                'calendar_event_uid' => $uid,
                'error' => $cleanup->getMessage(),
            ]);
        }
    }
}
