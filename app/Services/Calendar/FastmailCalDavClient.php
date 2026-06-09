<?php

namespace App\Services\Calendar;

use App\Contracts\Calendar\CalendarEventDraft;
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

        $raw = [];

        foreach (CalDavXmlParser::parsePropfindResponses($response) as $item) {
            $href = $item['href'];

            if ($href === $home || ! str_contains($href, '/')) {
                continue;
            }

            $url = str_starts_with($href, 'http') ? $href : self::BASE_URL.$href;
            $id = basename(rtrim(parse_url($url, PHP_URL_PATH) ?? '', '/'));

            if ($id === '' || $id === rawurlencode($this->username)) {
                continue;
            }

            $displayName = filled($item['displayname'])
                ? $item['displayname']
                : str_replace(['%40', '%20'], ['@', ' '], $id);

            $raw[] = [
                'id' => $id,
                'display_name' => $displayName,
                'url' => rtrim($url, '/').'/',
            ];
        }

        return $this->formatCalendarLabels($raw);
    }

    /**
     * @param  list<array{id: string, display_name: string, url: string}>  $calendars
     * @return list<array{id: string, name: string, url: string}>
     */
    private function formatCalendarLabels(array $calendars): array
    {
        $counts = array_count_values(array_column($calendars, 'display_name'));

        return array_map(function (array $calendar) use ($counts): array {
            $name = $calendar['display_name'];

            if (($counts[$calendar['display_name']] ?? 0) > 1) {
                $name .= ' ('.substr($calendar['id'], 0, 8).')';
            }

            return [
                'id' => $calendar['id'],
                'name' => $name,
                'url' => $calendar['url'],
            ];
        }, $calendars);
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

        foreach (CalDavXmlParser::extractCalendarDataBlocks($response) as $ics) {
            foreach (CalDavXmlParser::parseEventBusyTimes($ics) as $interval) {
                $busy[] = $interval;
            }
        }

        return $busy;
    }

    public function createEvent(string $calendarUrl, string $icsBody, string $uid): void
    {
        $eventUrl = $this->eventUrl($calendarUrl, $uid);

        $this->request('PUT', $eventUrl, $icsBody, [
            'If-None-Match' => '*',
        ], 'text/calendar; charset=utf-8');
    }

    public function deleteEvent(string $calendarUrl, string $uid): void
    {
        $this->request('DELETE', $this->eventUrl($calendarUrl, $uid), '', contentType: '');
    }

    private function eventUrl(string $calendarUrl, string $uid): string
    {
        return rtrim($calendarUrl, '/').'/'.rawurlencode($uid).'.ics';
    }

    public function buildEventIcs(
        CalendarEventDraft $draft,
        string $organizerEmail,
    ): string {
        return (new IcsEventBuilder)->build($draft, $organizerEmail);
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
    private function request(
        string $method,
        string $url,
        string $body,
        array $headers = [],
        string $contentType = 'application/xml',
    ): string {
        $pending = Http::withBasicAuth($this->username, $this->appPassword)
            ->withHeaders($headers);

        if ($contentType !== '') {
            $pending = $pending->withBody($body, $contentType);
        }

        $response = $pending->send($method, $url);

        if ($response->failed()) {
            throw new RequestException($response);
        }

        return $response->body();
    }
}
