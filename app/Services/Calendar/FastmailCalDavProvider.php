<?php

namespace App\Services\Calendar;

use App\Contracts\Calendar\CalendarEventDraft;
use App\Contracts\Calendar\CalendarProvider;
use App\Models\AgencyBookingSetting;
use Carbon\CarbonInterface;

class FastmailCalDavProvider implements CalendarProvider
{
    public function __construct(
        private AgencyBookingSetting $settings,
    ) {}

    public function busyIntervals(CarbonInterface $from, CarbonInterface $to): array
    {
        return $this->client()->busyIntervals(
            $this->settings->caldav_calendar_url,
            $from,
            $to,
        );
    }

    public function createEvent(CalendarEventDraft $draft): string
    {
        $client = $this->client();
        $ics = $client->buildEventIcs($draft, $this->settings->fastmail_username);
        $client->createEvent($this->settings->caldav_calendar_url, $ics, $draft->uid);

        return $draft->uid;
    }

    private function client(): FastmailCalDavClient
    {
        return new FastmailCalDavClient(
            $this->settings->fastmail_username,
            $this->settings->fastmail_app_password,
        );
    }
}
