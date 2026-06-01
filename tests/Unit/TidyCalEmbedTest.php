<?php

namespace Tests\Unit;

use App\Support\TidyCalEmbed;
use Tests\TestCase;

class TidyCalEmbedTest extends TestCase
{
    public function test_parses_tidycal_booking_page_url(): void
    {
        $this->assertSame('368j4y9', TidyCalEmbed::pathFromUrl('https://tidycal.com/368j4y9'));
        $this->assertSame('368j4y9', TidyCalEmbed::pathFromUrl('https://www.tidycal.com/368j4y9/'));
    }

    public function test_parses_tidycal_booking_type_url(): void
    {
        $this->assertSame(
            'ross/30-minute-review',
            TidyCalEmbed::pathFromUrl('https://tidycal.com/ross/30-minute-review'),
        );
    }

    public function test_returns_null_for_non_tidycal_urls(): void
    {
        $this->assertNull(TidyCalEmbed::pathFromUrl('https://calendly.com/acme/30min'));
        $this->assertNull(TidyCalEmbed::pathFromUrl(null));
        $this->assertNull(TidyCalEmbed::pathFromUrl(''));
    }

    public function test_book_page_url_uses_app_route_for_tidycal(): void
    {
        $url = TidyCalEmbed::bookPageUrl('https://tidycal.com/368j4y9', ['report' => 'abc']);

        $this->assertNotNull($url);
        $this->assertStringContainsString('/book', $url);
        $this->assertStringContainsString('report=abc', $url);
    }

    public function test_book_page_url_returns_external_url_for_other_providers(): void
    {
        $external = 'https://calendly.com/acme/30min';

        $this->assertSame($external, TidyCalEmbed::bookPageUrl($external));
    }
}
