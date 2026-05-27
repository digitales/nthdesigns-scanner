<?php

namespace Tests\Unit;

use App\Support\PlaywrightBrowsers;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PlaywrightBrowsersTest extends TestCase
{
    public function test_resolve_uses_configured_path_when_set(): void
    {
        Config::set('scanner.playwright_browsers_path', '/custom/browsers');

        $this->assertSame('/custom/browsers', PlaywrightBrowsers::resolve());
    }

    public function test_has_installed_browsers_false_for_missing_directory(): void
    {
        $this->assertFalse(PlaywrightBrowsers::hasInstalledBrowsers('/path/that/does/not/exist'));
    }

    public function test_resolve_falls_back_to_storage_when_present(): void
    {
        $storage = PlaywrightBrowsers::storageDirectory();

        if (!PlaywrightBrowsers::hasInstalledBrowsers($storage)) {
            $this->markTestSkipped('storage/app/playwright-browsers not installed');
        }

        Config::set('scanner.playwright_browsers_path', null);

        $this->assertSame($storage, PlaywrightBrowsers::resolve());
    }
}
