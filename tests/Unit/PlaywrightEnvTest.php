<?php

namespace Tests\Unit;

use App\Support\PlaywrightBrowsers;
use App\Support\PlaywrightEnv;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PlaywrightEnvTest extends TestCase
{
    public function test_for_process_includes_playwright_browsers_path_when_configured(): void
    {
        Config::set('scanner.playwright_browsers_path', '0');

        $env = PlaywrightEnv::forProcess(['LIGHTHOUSE_BINARY' => 'lighthouse']);

        $this->assertSame('0', $env['PLAYWRIGHT_BROWSERS_PATH']);
        $this->assertSame('lighthouse', $env['LIGHTHOUSE_BINARY']);
    }

    public function test_for_process_omits_playwright_browsers_path_when_unset(): void
    {
        Config::set('scanner.playwright_browsers_path', null);

        if (PlaywrightBrowsers::resolve() !== null) {
            $this->markTestSkipped('Playwright browsers are present on disk');
        }

        $env = PlaywrightEnv::forProcess();

        $this->assertArrayNotHasKey('PLAYWRIGHT_BROWSERS_PATH', $env);
    }

    public function test_for_process_falls_back_to_bundled_browsers_when_config_null(): void
    {
        if (!is_dir(base_path('scripts/node_modules/.cache/ms-playwright'))) {
            $this->markTestSkipped('Bundled Playwright browsers not installed');
        }

        Config::set('scanner.playwright_browsers_path', null);

        $env = PlaywrightEnv::forProcess();

        $this->assertSame('0', $env['PLAYWRIGHT_BROWSERS_PATH']);
    }
}
