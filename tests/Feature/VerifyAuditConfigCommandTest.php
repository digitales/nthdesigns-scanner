<?php

namespace Tests\Feature;

use App\Support\ScannerConfig;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VerifyAuditConfigCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('AUDIT_SERVICE_URL');
        unset($_ENV['AUDIT_SERVICE_URL'], $_SERVER['AUDIT_SERVICE_URL']);

        parent::tearDown();
    }

    public function test_reports_http_driver_success_when_browser_service_is_healthy(): void
    {
        putenv('AUDIT_SERVICE_URL=https://browser.example');
        $_ENV['AUDIT_SERVICE_URL'] = 'https://browser.example';
        $_SERVER['AUDIT_SERVICE_URL'] = 'https://browser.example';

        ScannerConfig::applyRuntimeOverrides();

        Config::set('scanner.audit_service_token', 'secret');

        Http::fake([
            'https://browser.example/health' => Http::response(['ok' => true]),
        ]);

        $this->artisan('scanner:verify-audit-config')
            ->expectsOutputToContain('audit_driver')
            ->expectsOutputToContain('http')
            ->assertSuccessful();
    }

    public function test_reports_failure_when_playwright_driver_has_missing_npm_packages(): void
    {
        if (is_dir(base_path('scripts/node_modules/playwright'))) {
            $this->markTestSkipped('Playwright npm packages are installed locally');
        }

        Config::set('scanner.audit_driver', 'playwright');

        $this->artisan('scanner:verify-audit-config')
            ->expectsOutputToContain('scripts/node_modules/playwright missing')
            ->expectsOutputToContain('AUDIT_SERVICE_URL=https://nth-scanner-browser.fly.dev')
            ->assertFailed();
    }
}
