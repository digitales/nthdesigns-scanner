<?php

namespace App\Contracts\Calendar;

use Carbon\CarbonInterface;

interface CalendarProvider
{
    /**
     * Busy intervals in UTC from the connected calendar.
     *
     * @return list<array{start: CarbonInterface, end: CarbonInterface}>
     */
    public function busyIntervals(CarbonInterface $from, CarbonInterface $to): array;

    public function createEvent(CalendarEventDraft $draft): string;

    public function deleteEvent(string $uid): void;
}
