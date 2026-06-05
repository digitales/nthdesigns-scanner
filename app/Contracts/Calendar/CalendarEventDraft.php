<?php

namespace App\Contracts\Calendar;

use Carbon\CarbonInterface;

readonly class CalendarEventDraft
{
    public function __construct(
        public CarbonInterface $startsAt,
        public CarbonInterface $endsAt,
        public string $summary,
        public string $description,
        public string $attendeeEmail,
        public string $attendeeName,
        public string $uid,
    ) {}
}
