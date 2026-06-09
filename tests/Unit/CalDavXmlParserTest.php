<?php

namespace Tests\Unit;

use App\Services\Calendar\CalDavXmlParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CalDavXmlParserTest extends TestCase
{
    #[Test]
    public function test_response_hrefs_extracts_unique_href_values(): void
    {
        $xml = <<<'XML'
<d:multistatus xmlns:d="DAV:">
  <d:response><d:href>/dav/calendars/user/me/</d:href></d:response>
  <d:response><d:href>/dav/calendars/user/me/work/</d:href></d:response>
  <d:response><d:href>/dav/calendars/user/me/work/</d:href></d:response>
</d:multistatus>
XML;

        $this->assertSame(
            ['/dav/calendars/user/me/', '/dav/calendars/user/me/work/'],
            CalDavXmlParser::responseHrefs($xml),
        );
    }

    #[Test]
    public function test_extract_calendar_data_blocks_decodes_namespaced_xml(): void
    {
        $xml = <<<'XML'
<d:multistatus xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
  <d:response>
    <c:calendar-data>BEGIN:VCALENDAR&#13;&#10;END:VCALENDAR</c:calendar-data>
  </d:response>
</d:multistatus>
XML;

        $blocks = CalDavXmlParser::extractCalendarDataBlocks($xml);

        $this->assertCount(1, $blocks);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $blocks[0]);
    }

    #[Test]
    public function test_parse_event_busy_times_returns_utc_interval(): void
    {
        $ics = <<<'ICS'
BEGIN:VCALENDAR
BEGIN:VEVENT
UID:test@example.com
DTSTART:20260610T100000Z
DTEND:20260610T110000Z
END:VEVENT
END:VCALENDAR
ICS;

        $intervals = CalDavXmlParser::parseEventBusyTimes($ics);

        $this->assertCount(1, $intervals);
        $this->assertSame('2026-06-10 10:00:00', $intervals[0]['start']->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-10 11:00:00', $intervals[0]['end']->utc()->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function test_parse_event_busy_times_ignores_cancelled_events(): void
    {
        $ics = <<<'ICS'
BEGIN:VEVENT
STATUS:CANCELLED
DTSTART:20260610T100000Z
DTEND:20260610T110000Z
END:VEVENT
ICS;

        $this->assertSame([], CalDavXmlParser::parseEventBusyTimes($ics));
    }

    #[Test]
    public function test_parse_event_busy_times_ignores_transparent_events(): void
    {
        $ics = <<<'ICS'
BEGIN:VEVENT
TRANSP:TRANSPARENT
DTSTART:20260610T100000Z
DTEND:20260610T110000Z
END:VEVENT
ICS;

        $this->assertSame([], CalDavXmlParser::parseEventBusyTimes($ics));
    }

    #[Test]
    public function test_parse_propfind_responses_extracts_href_and_displayname(): void
    {
        $xml = <<<'XML'
<d:multistatus xmlns:d="DAV:">
  <d:response>
    <d:href>/dav/calendars/user/me/</d:href>
    <d:propstat><d:prop><d:displayname>Home</d:displayname></d:prop></d:propstat>
  </d:response>
  <d:response>
    <d:href>/dav/calendars/user/me/3f2a1b9c-e4d5-6789-abcd-ef0123456789/</d:href>
    <d:propstat><d:prop><d:displayname>Work</d:displayname></d:prop></d:propstat>
  </d:response>
</d:multistatus>
XML;

        $this->assertSame([
            ['href' => '/dav/calendars/user/me/', 'displayname' => 'Home'],
            ['href' => '/dav/calendars/user/me/3f2a1b9c-e4d5-6789-abcd-ef0123456789/', 'displayname' => 'Work'],
        ], CalDavXmlParser::parsePropfindResponses($xml));
    }

    #[Test]
    public function test_parse_propfind_responses_returns_null_when_displayname_missing(): void
    {
        $xml = <<<'XML'
<d:multistatus xmlns:d="DAV:">
  <d:response>
    <d:href>/dav/calendars/user/me/uuid-here/</d:href>
    <d:propstat><d:prop></d:prop></d:propstat>
  </d:response>
</d:multistatus>
XML;

        $this->assertSame([
            ['href' => '/dav/calendars/user/me/uuid-here/', 'displayname' => null],
        ], CalDavXmlParser::parsePropfindResponses($xml));
    }

    #[Test]
    public function test_parse_propfind_responses_decodes_xml_entities_in_displayname(): void
    {
        $xml = <<<'XML'
<d:multistatus xmlns:d="DAV:">
  <d:response>
    <d:href>/dav/calendars/user/me/abc/</d:href>
    <d:propstat><d:prop><d:displayname>Tom &amp; Jerry</d:displayname></d:prop></d:propstat>
  </d:response>
</d:multistatus>
XML;

        $this->assertSame('Tom & Jerry', CalDavXmlParser::parsePropfindResponses($xml)[0]['displayname']);
    }

    #[Test]
    public function test_parse_event_busy_times_supports_all_day_dates(): void
    {
        $ics = <<<'ICS'
BEGIN:VEVENT
DTSTART;VALUE=DATE:20260610
DTEND;VALUE=DATE:20260611
END:VEVENT
ICS;

        $intervals = CalDavXmlParser::parseEventBusyTimes($ics);

        $this->assertCount(1, $intervals);
        $this->assertSame('2026-06-10 00:00:00', $intervals[0]['start']->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-11 00:00:00', $intervals[0]['end']->utc()->format('Y-m-d H:i:s'));
    }
}
