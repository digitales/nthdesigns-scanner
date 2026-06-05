<?php

namespace App\Services\Calendar;

use App\Contracts\Calendar\CalendarEventDraft;
use App\Contracts\Calendar\CalendarProvider;
use App\Models\AgencyBookingSetting;
use Carbon\CarbonInterface;

class FastmailCalDavProvider implements CalendarProvider
{
    public function busyIntervals(CarbonInterface $from, CarbonInterface $to): array
    {
        $settings = AgencyBookingSetting::current();

        return $this->client($settings)->busyIntervals(
            $settings->caldav_calendar_url,
            $from,
            $to,
        );
    }

    public function createEvent(CalendarEventDraft $draft): string
    {
        $settings = AgencyBookingSetting::current();
        $client = $this->client($settings);
        $ics = $client->buildEventIcs($draft, $settings->fastmail_username);
        $client->createEvent($settings->caldav_calendar_url, $ics, $draft->uid);

        return $draft->uid;
    }

    public function deleteEvent(string $uid): void
    {
        $settings = AgencyBookingSetting::current();
        $this->client($settings)->deleteEvent($settings->caldav_calendar_url, $uid);
    }

    private function client(AgencyBookingSetting $settings): FastmailCalDavClient
    {
        return new FastmailCalDavClient(
            $settings->fastmail_username,
            $settings->fastmail_app_password,
        );
    }
}
