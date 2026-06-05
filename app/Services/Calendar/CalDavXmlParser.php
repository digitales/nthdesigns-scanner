<?php

namespace App\Services\Calendar;

use Carbon\Carbon;
use Carbon\CarbonInterface;

final class CalDavXmlParser
{
    /**
     * @return list<string>
     */
    public static function responseHrefs(string $xml): array
    {
        preg_match_all('/<[^>]*href[^>]*>([^<]+)<\/[^>]*href>/i', $xml, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * @return list<string>
     */
    public static function extractCalendarDataBlocks(string $xml): array
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
    public static function parseEventBusyTimes(string $ics): array
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

        $start = self::parseIcsDateTime($event, 'DTSTART');
        $end = self::parseIcsDateTime($event, 'DTEND');

        if (! $start || ! $end) {
            return [];
        }

        return [['start' => $start, 'end' => $end]];
    }

    private static function parseIcsDateTime(string $event, string $property): ?CarbonInterface
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
}
