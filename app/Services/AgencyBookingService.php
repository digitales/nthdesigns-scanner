<?php

namespace App\Services;

use App\Models\AgencyBookingSetting;
use App\Services\Calendar\FastmailCalDavClient;
use Illuminate\Http\Client\RequestException;

class AgencyBookingService
{
    public function settings(): AgencyBookingSetting
    {
        return AgencyBookingSetting::current();
    }

    public function nativeBookingActive(): bool
    {
        return $this->settings()->nativeBookingActive();
    }

    /**
     * @return array{ok: bool, message: string, calendars?: list<array{id: string, name: string, url: string}>}
     */
    public function testConnection(
        string $username,
        string $appPassword,
    ): array {
        try {
            $calendars = (new FastmailCalDavClient($username, $appPassword))->listCalendars();

            if ($calendars === []) {
                return ['ok' => false, 'message' => 'Connected, but no calendars were found.'];
            }

            return [
                'ok' => true,
                'message' => 'Connected. '.count($calendars).' calendar(s) found.',
                'calendars' => $calendars,
            ];
        } catch (RequestException $e) {
            return ['ok' => false, 'message' => 'Could not connect to Fastmail CalDAV. Check username and app password.'];
        }
    }
}
