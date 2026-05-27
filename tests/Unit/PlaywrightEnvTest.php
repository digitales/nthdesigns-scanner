<?php

namespace Tests\Unit;

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

        $env = PlaywrightEnv::forProcess();

        $this->assertArrayNotHasKey('PLAYWRIGHT_BROWSERS_PATH', $env);
    }
}
