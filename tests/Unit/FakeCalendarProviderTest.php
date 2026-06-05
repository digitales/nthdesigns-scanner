<?php

namespace Tests\Unit;

use App\Contracts\Calendar\CalendarEventDraft;
use App\Services\Calendar\FakeCalendarProvider;
use Carbon\Carbon;
use Tests\TestCase;

class FakeCalendarProviderTest extends TestCase
{
    public function test_delete_event_removes_created_event_and_busy_slot(): void
    {
        $calendar = new FakeCalendarProvider;
        $draft = new CalendarEventDraft(
            startsAt: Carbon::parse('2026-06-11 14:00:00', 'Europe/London'),
            endsAt: Carbon::parse('2026-06-11 14:30:00', 'Europe/London'),
            summary: 'Review call',
            description: 'Test',
            attendeeEmail: 'jane@example.com',
            attendeeName: 'Jane Smith',
            uid: 'scanner-test@nthdesigns.co.uk',
        );

        $calendar->createEvent($draft);
        $calendar->deleteEvent($draft->uid);

        $this->assertCount(0, $calendar->createdEvents());
        $this->assertSame([$draft->uid], $calendar->deletedUids());
    }
}
