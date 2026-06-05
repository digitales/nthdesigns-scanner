<?php

namespace App\Services\Calendar;

use App\Contracts\Calendar\CalendarEventDraft;
use App\Contracts\Calendar\CalendarProvider;
use Carbon\CarbonInterface;

class FakeCalendarProvider implements CalendarProvider
{
    /** @var list<array{start: CarbonInterface, end: CarbonInterface}> */
    private array $busy = [];

    /** @var list<CalendarEventDraft> */
    private array $created = [];

    /**
     * @param  list<array{start: CarbonInterface, end: CarbonInterface}>  $busy
     */
    public function setBusyIntervals(array $busy): void
    {
        $this->busy = $busy;
    }

    public function busyIntervals(CarbonInterface $from, CarbonInterface $to): array
    {
        return array_values(array_filter(
            $this->busy,
            fn (array $interval) => $interval['start'] < $to && $interval['end'] > $from,
        ));
    }

    public function createEvent(CalendarEventDraft $draft): string
    {
        $this->created[] = $draft;
        $this->busy[] = [
            'start' => $draft->startsAt,
            'end' => $draft->endsAt,
        ];

        return $draft->uid;
    }

    /** @return list<CalendarEventDraft> */
    public function createdEvents(): array
    {
        return $this->created;
    }
}
