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

    private ?\Throwable $createException = null;

    private ?\Throwable $busyException = null;

    /** @var list<string> */
    private array $deletedUids = [];

    /**
     * @param  list<array{start: CarbonInterface, end: CarbonInterface}>  $busy
     */
    public function setBusyIntervals(array $busy): void
    {
        $this->busy = $busy;
    }

    public function failOnBusy(\Throwable $exception): void
    {
        $this->busyException = $exception;
    }

    public function busyIntervals(CarbonInterface $from, CarbonInterface $to): array
    {
        if ($this->busyException) {
            throw $this->busyException;
        }

        return array_values(array_filter(
            $this->busy,
            fn (array $interval) => $interval['start'] < $to && $interval['end'] > $from,
        ));
    }

    public function failOnCreate(\Throwable $exception): void
    {
        $this->createException = $exception;
    }

    public function createEvent(CalendarEventDraft $draft): string
    {
        if ($this->createException) {
            throw $this->createException;
        }

        $this->created[] = $draft;
        $this->busy[] = [
            'start' => $draft->startsAt,
            'end' => $draft->endsAt,
        ];

        return $draft->uid;
    }

    public function deleteEvent(string $uid): void
    {
        $this->deletedUids[] = $uid;

        foreach ($this->created as $index => $draft) {
            if ($draft->uid !== $uid) {
                continue;
            }

            unset($this->created[$index]);
            $this->busy = array_values(array_filter(
                $this->busy,
                fn (array $interval) => ! ($interval['start']->eq($draft->startsAt) && $interval['end']->eq($draft->endsAt)),
            ));

            break;
        }

        $this->created = array_values($this->created);
    }

    /** @return list<string> */
    public function deletedUids(): array
    {
        return $this->deletedUids;
    }

    /** @return list<CalendarEventDraft> */
    public function createdEvents(): array
    {
        return $this->created;
    }
}
