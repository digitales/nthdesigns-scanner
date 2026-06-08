<?php

namespace Tests\Unit;

use App\Services\ApiHealthService;
use App\Support\PlaywrightScripts;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ApiHealthServicePlaywrightTest extends TestCase
{
    public function test_check_playwright_fails_when_npm_packages_missing(): void
    {
        if (PlaywrightScripts::dependenciesInstalled()) {
            $this->markTestSkipped('Playwright npm packages are installed locally');
        }

        Config::set('scanner.audit_driver', 'playwright');

        $result = app(ApiHealthService::class)->checkAll()['playwright'] ?? null;

        $this->assertNotNull($result);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('scripts/node_modules/playwright missing', $result['message']);
    }
}
