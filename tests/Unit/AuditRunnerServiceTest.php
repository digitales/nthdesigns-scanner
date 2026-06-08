<?php

namespace Tests\Unit;

use App\Services\AuditRunnerService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuditRunnerServiceTest extends TestCase
{
    public function test_should_skip_when_audit_driver_is_skip(): void
    {
        Config::set('scanner.audit_driver', 'skip');

        $this->assertTrue(app(AuditRunnerService::class)->shouldSkip());
    }

    public function test_http_driver_fetches_audit_payload_from_service(): void
    {
        Config::set('scanner.audit_driver', 'http');
        Config::set('scanner.audit_service_url', 'https://audit.example.com');
        Config::set('scanner.audit_service_token', 'secret');
        Config::set('scanner.audit_timeout', 120);

        $dir = storage_path('app/temp/audit-runner-http-test');
        @mkdir($dir, 0755, true);

        $png = base64_encode('violation-png');

        Http::fake([
            'https://audit.example.com/audit' => Http::response([
                'url' => 'https://example.com',
                'violations' => [],
                'pass_count' => 10,
                'incomplete_count' => 0,
                'violation_screenshots' => [
                    [
                        'violation_id' => 'color-contrast',
                        'index' => 0,
                        'file' => 'violation-0.png',
                        'content_base64' => $png,
                    ],
                ],
                'lighthouse' => null,
            ]),
        ]);

        try {
            $payload = app(AuditRunnerService::class)->run(
                'https://example.com',
                $dir,
            );
        } finally {
            @unlink($dir.'/violation-0.png');
            @rmdir($dir);
        }

        $this->assertSame('https://example.com', $payload['url']);
        $this->assertSame([], $payload['violations']);
        $this->assertArrayNotHasKey('content_base64', $payload['violation_screenshots'][0]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://audit.example.com/audit'
                && $request['url'] === 'https://example.com'
                && $request->hasHeader('Authorization', 'Bearer secret');
        });
    }

    public function test_http_driver_returns_page_load_error_payload(): void
    {
        Config::set('scanner.audit_driver', 'http');
        Config::set('scanner.audit_service_url', 'https://audit.example.com');
        Config::set('scanner.audit_service_token', 'secret');

        Http::fake([
            'https://audit.example.com/audit' => Http::response([
                'url' => 'http://www.forkidsco.com/',
                'error' => 'page.goto: Timeout 45000ms exceeded.',
                'violations' => [],
                'violation_screenshots' => [],
            ]),
        ]);

        $dir = storage_path('app/temp/audit-runner-error-test');
        @mkdir($dir, 0755, true);

        try {
            $payload = app(AuditRunnerService::class)->run('http://www.forkidsco.com/', $dir);
        } finally {
            @rmdir($dir);
        }

        $this->assertSame('page.goto: Timeout 45000ms exceeded.', $payload['error']);
    }

    public function test_playwright_driver_fails_fast_when_npm_packages_missing(): void
    {
        if (is_dir(base_path('scripts/node_modules/playwright'))) {
            $this->markTestSkipped('Playwright npm packages are installed locally');
        }

        Config::set('scanner.audit_driver', 'playwright');
        Config::set('scanner.node_binary', PHP_BINARY);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('scripts/node_modules/playwright missing');

        app(AuditRunnerService::class)->run(
            'https://example.com',
            storage_path('app/temp/audit-runner-missing-deps-test'),
        );
    }
}
