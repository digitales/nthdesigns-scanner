<?php

namespace Tests\Unit;

use App\Support\ScannerConfig;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ScannerConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('AUDIT_SERVICE_URL');
        putenv('AUDIT_SERVICE_TOKEN');
        putenv('AUDIT_DRIVER');
        putenv('SCREENSHOT_DRIVER');

        unset($_ENV['AUDIT_SERVICE_URL'], $_SERVER['AUDIT_SERVICE_URL']);
        unset($_ENV['AUDIT_SERVICE_TOKEN'], $_SERVER['AUDIT_SERVICE_TOKEN']);
        unset($_ENV['AUDIT_DRIVER'], $_SERVER['AUDIT_DRIVER']);
        unset($_ENV['SCREENSHOT_DRIVER'], $_SERVER['SCREENSHOT_DRIVER']);

        parent::tearDown();
    }

    public function test_audit_service_url_overrides_cached_playwright_driver(): void
    {
        Config::set('scanner.audit_driver', 'playwright');
        Config::set('scanner.screenshot_driver', 'playwright');
        Config::set('scanner.audit_service_url', null);

        putenv('AUDIT_SERVICE_URL=https://browser.example');
        $_ENV['AUDIT_SERVICE_URL'] = 'https://browser.example';
        $_SERVER['AUDIT_SERVICE_URL'] = 'https://browser.example';

        putenv('AUDIT_SERVICE_TOKEN=secret');
        $_ENV['AUDIT_SERVICE_TOKEN'] = 'secret';
        $_SERVER['AUDIT_SERVICE_TOKEN'] = 'secret';

        ScannerConfig::applyRuntimeOverrides();

        $this->assertSame('http', config('scanner.audit_driver'));
        $this->assertSame('http', config('scanner.screenshot_driver'));
        $this->assertSame('https://browser.example', config('scanner.audit_service_url'));
        $this->assertSame('secret', config('scanner.audit_service_token'));
    }
}
