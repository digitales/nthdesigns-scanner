<?php

namespace App\Services\Calendar;

use App\Contracts\Calendar\CalendarEventDraft;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class FastmailCalDavClient
{
    private const BASE_URL = 'https://caldav.fastmail.com';

    public function __construct(
        private string $username,
        private string $appPassword,
    ) {}

    /**
     * @return list<array{id: string, name: string, url: string}>
     */
    public function listCalendars(): array
    {
        $home = self::BASE_URL.'/dav/calendars/user/'.rawurlencode($this->username).'/';

        $response = $this->request('PROPFIND', $home, $this->propfindCalendarsBody(), [
            'Depth' => '1',
        ]);

        $calendars = [];

        foreach ($this->responseHrefs($response) as $href) {
            if ($href === $home || ! str_contains($href, '/')) {
                continue;
            }

            $url = str_starts_with($href, 'http') ? $href : self::BASE_URL.$href;
            $name = basename(rtrim(parse_url($url, PHP_URL_PATH) ?? '', '/'));

            if ($name === '' || $name === rawurlencode($this->username)) {
                continue;
            }

            $calendars[] = [
                'id' => $name,
                'name' => str_replace(['%40', '%20'], ['@', ' '], $name),
                'url' => rtrim($url, '/').'/',
            ];
        }

        return $calendars;
    }

    /**
     * @return list<array{start: CarbonInterface, end: CarbonInterface}>
     */
    public function busyIntervals(string $calendarUrl, CarbonInterface $from, CarbonInterface $to): array
    {
        $response = $this->request('REPORT', $calendarUrl, $this->calendarQueryBody($from, $to), [
            'Depth' => '1',
        ]);

        $busy = [];

        foreach ($this->extractCalendarDataBlocks($response) as $ics) {
            foreach ($this->parseEventBusyTimes($ics) as $interval) {
                $busy[] = $interval;
            }
        }

        return $busy;
    }

    public function createEvent(string $calendarUrl, string $icsBody, string $uid): void
    {
        $eventUrl = rtrim($calendarUrl, '/').'/'.rawurlencode($uid).'.ics';

        $this->request('PUT', $eventUrl, $icsBody, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'If-None-Match' => '*',
        ]);
    }

    public function buildEventIcs(
        CalendarEventDraft $draft,
        string $organizerEmail,
    ): string {
        $dtStamp = Carbon::now('UTC')->format('Ymd\THis\Z');
        $dtStart = $draft->startsAt->copy()->utc()->format('Ymd\THis\Z');
        $dtEnd = $draft->endsAt->copy()->utc()->format('Ymd\THis\Z');
        $summary = $this->escapeIcsText($draft->summary);
        $description = $this->escapeIcsText($draft->description);
        $attendeeName = $this->escapeIcsText($draft->attendeeName);

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

    /**
     * @return list<string>
     */
    private function responseHrefs(string $xml): array
    {
        preg_match_all('/<[^>]*href[^>]*>([^<]+)<\/[^>]*href>/i', $xml, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * @return list<string>
     */
    private function extractCalendarDataBlocks(string $xml): array
    {
        preg_match_all('/<(?:[^:>]+:)?calendar-data[^>]*>([\s\S]*?)<\/(?:[^:>]+:)?calendar-data>/i', $xml, $matches);

        return array_map(
            fn (string $block) => html_entity_decode(trim($block), ENT_XML1),
            $matches[1] ?? [],
        );
    }

    /**
     * @return list<array{start: CarbonInterface, end: CarbonInterface}>
     */
    private function parseEventBusyTimes(string $ics): array
    {
        if (! preg_match('/BEGIN:VEVENT([\s\S]*?)END:VEVENT/i', $ics, $eventMatch)) {
            return [];
        }

        $event = $eventMatch[1];

        if (preg_match('/STATUS:CANCELLED/i', $event)) {
            return [];
        }

        if (preg_match('/TRANSP:TRANSPARENT/i', $event)) {
            return [];
        }

        $start = $this->parseIcsDateTime($event, 'DTSTART');
        $end = $this->parseIcsDateTime($event, 'DTEND');

        if (! $start || ! $end) {
            return [];
        }

        return [['start' => $start, 'end' => $end]];
    }

    private function parseIcsDateTime(string $event, string $property): ?CarbonInterface
    {
        if (! preg_match('/'.$property.'(?:;[^:]*)?:(.+)/i', $event, $match)) {
            return null;
        }

        $value = trim($match[1]);

        if (preg_match('/^\d{8}T\d{6}Z$/', $value)) {
            return Carbon::createFromFormat('Ymd\THis\Z', $value, 'UTC');
        }

        if (preg_match('/^\d{8}$/', $value)) {
            return Carbon::createFromFormat('Ymd', $value, 'UTC')->startOfDay();
        }

        return null;
    }

    private function propfindCalendarsBody(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<d:propfind xmlns:d="DAV:" xmlns:cs="http://calendarserver.org/ns/">
  <d:prop>
    <d:displayname/>
    <cs:getctag/>
  </d:prop>
</d:propfind>
XML;
    }

    private function calendarQueryBody(CarbonInterface $from, CarbonInterface $to): string
    {
        $start = $from->copy()->utc()->format('Ymd\THis\Z');
        $end = $to->copy()->utc()->format('Ymd\THis\Z');

        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
  <d:prop>
    <d:getetag/>
    <c:calendar-data/>
  </d:prop>
  <c:filter>
    <c:comp-filter name="VCALENDAR">
      <c:comp-filter name="VEVENT">
        <c:time-range start="{$start}" end="{$end}"/>
      </c:comp-filter>
    </c:comp-filter>
  </c:filter>
</c:calendar-query>
XML;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function request(string $method, string $url, string $body, array $headers = []): string
    {
        $response = Http::withBasicAuth($this->username, $this->appPassword)
            ->withHeaders($headers)
            ->withBody($body, 'application/xml')
            ->send($method, $url);

        if ($response->failed()) {
            throw new RequestException($response);
        }

        return $response->body();
    }

    private function escapeIcsText(string $value): string
    {
        return str_replace(
            ["\r\n", "\n", "\r", ',', ';'],
            ['\\n', '\\n', '\\n', '\\,', '\\;'],
            $value,
        );
    }
}
