<?php

namespace Tests\Unit;

use App\Services\Calendar\FastmailCalDavClient;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FastmailCalDavClientTest extends TestCase
{
    #[Test]
    public function test_list_calendars_uses_displayname_for_unique_labels(): void
    {
        Http::fake([
            'caldav.fastmail.com/*' => Http::response($this->multistatusXml([
                ['id' => '3f2a1b9c-e4d5-6789-abcd-ef0123456789', 'displayname' => 'Work'],
                ['id' => '8a1b2c3d-1111-2222-3333-444455556666', 'displayname' => 'Personal'],
            ]), 207),
        ]);

        $calendars = (new FastmailCalDavClient('bookings@example.com', 'secret'))->listCalendars();

        $this->assertSame([
            ['id' => '3f2a1b9c-e4d5-6789-abcd-ef0123456789', 'name' => 'Work', 'url' => 'https://caldav.fastmail.com/dav/calendars/user/bookings%40example.com/3f2a1b9c-e4d5-6789-abcd-ef0123456789/'],
            ['id' => '8a1b2c3d-1111-2222-3333-444455556666', 'name' => 'Personal', 'url' => 'https://caldav.fastmail.com/dav/calendars/user/bookings%40example.com/8a1b2c3d-1111-2222-3333-444455556666/'],
        ], $calendars);
    }

    #[Test]
    public function test_list_calendars_appends_short_id_when_display_names_collide(): void
    {
        Http::fake([
            'caldav.fastmail.com/*' => Http::response($this->multistatusXml([
                ['id' => '3f2a1b9c-e4d5-6789-abcd-ef0123456789', 'displayname' => 'Work'],
                ['id' => '8a1b2c3d-1111-2222-3333-444455556666', 'displayname' => 'Work'],
            ]), 207),
        ]);

        $calendars = (new FastmailCalDavClient('bookings@example.com', 'secret'))->listCalendars();

        $this->assertSame('Work (3f2a1b9c)', $calendars[0]['name']);
        $this->assertSame('Work (8a1b2c3d)', $calendars[1]['name']);
    }

    #[Test]
    public function test_list_calendars_unwraps_cdata_displaynames(): void
    {
        Http::fake([
            'caldav.fastmail.com/*' => Http::response($this->multistatusXml([
                ['id' => '3f2a1b9c-e4d5-6789-abcd-ef0123456789', 'displayname' => '<![CDATA[Nthdesigns Scanner]]>'],
            ]), 207),
        ]);

        $calendars = (new FastmailCalDavClient('bookings@example.com', 'secret'))->listCalendars();

        $this->assertSame('Nthdesigns Scanner', $calendars[0]['name']);
    }

    #[Test]
    public function test_list_calendars_falls_back_to_id_when_displayname_missing(): void
    {
        Http::fake([
            'caldav.fastmail.com/*' => Http::response($this->multistatusXml([
                ['id' => '3f2a1b9c-e4d5-6789-abcd-ef0123456789', 'displayname' => null],
            ]), 207),
        ]);

        $calendars = (new FastmailCalDavClient('bookings@example.com', 'secret'))->listCalendars();

        $this->assertSame('3f2a1b9c-e4d5-6789-abcd-ef0123456789', $calendars[0]['name']);
    }

    /**
     * @param  list<array{id: string, displayname: ?string}>  $calendars
     */
    private function multistatusXml(array $calendars): string
    {
        $responses = <<<'XML'
  <d:response>
    <d:href>/dav/calendars/user/bookings%40example.com/</d:href>
    <d:propstat><d:prop><d:displayname>Home</d:displayname></d:prop></d:propstat>
  </d:response>
XML;

        foreach ($calendars as $calendar) {
            $displayname = $calendar['displayname'] ?? '';
            $responses .= <<<XML

  <d:response>
    <d:href>/dav/calendars/user/bookings%40example.com/{$calendar['id']}/</d:href>
    <d:propstat><d:prop><d:displayname>{$displayname}</d:displayname></d:prop></d:propstat>
  </d:response>
XML;
        }

        return '<d:multistatus xmlns:d="DAV:">'.$responses."\n</d:multistatus>";
    }
}
