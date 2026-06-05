<?php

namespace Tests\Unit;

use App\Contracts\Calendar\CalendarEventDraft;
use App\Services\Calendar\IcsEventBuilder;
use Carbon\Carbon;
use Tests\TestCase;

class IcsEventBuilderTest extends TestCase
{
    public function test_builds_valid_vevent_with_escaped_text(): void
    {
        $draft = new CalendarEventDraft(
            startsAt: Carbon::parse('2026-06-11 14:00:00', 'Europe/London'),
            endsAt: Carbon::parse('2026-06-11 14:30:00', 'Europe/London'),
            summary: 'Review call — Acme, Ltd',
            description: "Line one\nLine two",
            attendeeEmail: 'jane@example.com',
            attendeeName: 'Jane Smith',
            uid: 'scanner-test@nthdesigns.co.uk',
        );

        $ics = (new IcsEventBuilder)->build($draft, 'bookings@example.com');

        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('UID:scanner-test@nthdesigns.co.uk', $ics);
        $this->assertStringContainsString('SUMMARY:Review call — Acme\\, Ltd', $ics);
        $this->assertStringContainsString('ATTENDEE', $ics);
        $this->assertStringContainsString('mailto:jane@example.com', $ics);
    }
}
