<?php

namespace App\Services\Calendar;

use App\Contracts\Calendar\CalendarEventDraft;
use Carbon\Carbon;

final class IcsEventBuilder
{
    public function build(CalendarEventDraft $draft, string $organizerEmail): string
    {
        $dtStamp = Carbon::now('UTC')->format('Ymd\THis\Z');
        $dtStart = $draft->startsAt->copy()->utc()->format('Ymd\THis\Z');
        $dtEnd = $draft->endsAt->copy()->utc()->format('Ymd\THis\Z');
        $summary = $this->escapeText($draft->summary);
        $description = $this->escapeText($draft->description);
        $attendeeName = $this->escapeText($draft->attendeeName);

        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//nthdesigns//scanner//EN',
            'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            'UID:'.$draft->uid,
            'DTSTAMP:'.$dtStamp,
            'DTSTART:'.$dtStart,
            'DTEND:'.$dtEnd,
            'SUMMARY:'.$summary,
            'DESCRIPTION:'.$description,
            'ORGANIZER;CN=nthdesigns:mailto:'.$organizerEmail,
            'ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN='.$attendeeName.':mailto:'.$draft->attendeeEmail,
            'END:VEVENT',
            'END:VCALENDAR',
        ])."\r\n";
    }

    private function escapeText(string $value): string
    {
        return str_replace(
            ["\r\n", "\n", "\r", ',', ';'],
            ['\\n', '\\n', '\\n', '\\,', '\\;'],
            $value,
        );
    }
}
