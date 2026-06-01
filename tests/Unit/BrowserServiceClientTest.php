<?php

namespace Tests\Unit;

use App\Services\BrowserServiceClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BrowserServiceClientTest extends TestCase
{
    public function test_fetch_cms_detection_returns_payload(): void
    {
        Config::set('scanner.audit_service_url', 'https://browser.example.com');
        Config::set('scanner.audit_service_token', 'secret');

        Http::fake([
            'https://browser.example.com/detect-cms' => Http::response([
                'platform' => 'wordpress',
                'version' => '6.4.2',
                'confidence' => 'high',
                'signals' => [],
                'detected_at' => now()->toIso8601String(),
                'url' => 'https://example.com',
            ]),
        ]);

        $payload = app(BrowserServiceClient::class)->fetchCmsDetection('https://example.com');

        $this->assertSame('wordpress', $payload['platform']);
    }

    public function test_fetch_audit_returns_payload(): void
    {
        Config::set('scanner.audit_service_url', 'https://browser.example.com');
        Config::set('scanner.audit_service_token', 'secret');
        Config::set('scanner.audit_timeout', 120);

        Http::fake([
            'https://browser.example.com/audit' => Http::response([
                'url' => 'https://example.com',
                'violations' => [],
                'violation_screenshots' => [],
            ]),
        ]);

        $payload = app(BrowserServiceClient::class)->fetchAudit('https://example.com');

        $this->assertSame('https://example.com', $payload['url']);
    }

    public function test_materialize_violation_screenshots_writes_files(): void
    {
        $dir = storage_path('app/temp/browser-client-test');
        @mkdir($dir, 0755, true);

        $png = base64_encode('fake-png');

        try {
            $payload = app(BrowserServiceClient::class)->materializeViolationScreenshots([
                'violation_screenshots' => [
                    [
                        'violation_id' => 'color-contrast',
                        'index' => 0,
                        'file' => 'violation-0.png',
                        'content_base64' => $png,
                    ],
                ],
            ], $dir);

            $this->assertFileExists($dir.'/violation-0.png');
            $this->assertSame('fake-png', file_get_contents($dir.'/violation-0.png'));
            $this->assertArrayNotHasKey('content_base64', $payload['violation_screenshots'][0]);
        } finally {
            @unlink($dir.'/violation-0.png');
            @rmdir($dir);
        }
    }

    public function test_capture_desktop_surfaces_page_error(): void
    {
        Config::set('scanner.audit_service_url', 'https://browser.example.com');
        Config::set('scanner.audit_service_token', 'secret');
        Config::set('scanner.screenshot_timeout', 120);

        Http::fake([
            'https://browser.example.com/screenshot' => Http::response([
                'error' => 'page.screenshot: Timeout 30000ms exceeded.',
            ]),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('page.screenshot: Timeout 30000ms exceeded.');

        $dir = storage_path('app/temp/browser-screenshot-error-test');
        @mkdir($dir, 0755, true);

        try {
            app(BrowserServiceClient::class)->captureDesktop('https://example.com', $dir);
        } finally {
            @rmdir($dir);
        }
    }

    public function test_capture_desktop_writes_png(): void
    {
        Config::set('scanner.audit_service_url', 'https://browser.example.com');
        Config::set('scanner.audit_service_token', 'secret');

        $encoded = base64_encode('desktop-bytes');

        Http::fake([
            'https://browser.example.com/screenshot' => Http::response([
                'desktop' => 'desktop.png',
                'content_base64' => $encoded,
            ]),
        ]);

        $dir = storage_path('app/temp/browser-screenshot-test');
        @mkdir($dir, 0755, true);

        try {
            $path = app(BrowserServiceClient::class)->captureDesktop('https://example.com', $dir);

            $this->assertSame($dir.'/desktop.png', $path);
            $this->assertSame('desktop-bytes', file_get_contents($path));
        } finally {
            @unlink($dir.'/desktop.png');
            @rmdir($dir);
        }
    }

    public function test_fetch_audit_returns_page_load_error_payload(): void
    {
        Config::set('scanner.audit_service_url', 'https://browser.example.com');
        Config::set('scanner.audit_service_token', 'secret');

        Http::fake([
            'https://browser.example.com/audit' => Http::response([
                'url' => 'http://www.forkidsco.com/',
                'error' => 'page.goto: Timeout 45000ms exceeded.',
                'violations' => [],
                'pass_count' => 0,
                'incomplete_count' => 0,
                'violation_screenshots' => [],
                'lighthouse' => null,
            ]),
        ]);

        $payload = app(BrowserServiceClient::class)->fetchAudit('http://www.forkidsco.com/');

        $this->assertSame('page.goto: Timeout 45000ms exceeded.', $payload['error']);
        $this->assertSame([], $payload['violations']);
    }

    public function test_fetch_audit_parses_legacy_500_error_wrapper(): void
    {
        Config::set('scanner.audit_service_url', 'https://browser.example.com');
        Config::set('scanner.audit_service_token', 'secret');

        $inner = json_encode([
            'url' => 'http://www.forkidsco.com/',
            'error' => 'page.goto: Timeout 45000ms exceeded.',
            'violations' => [],
        ]);

        Http::fake([
            'https://browser.example.com/audit' => Http::response(
                ['error' => $inner],
                500,
            ),
        ]);

        $payload = app(BrowserServiceClient::class)->fetchAudit('http://www.forkidsco.com/');

        $this->assertSame('page.goto: Timeout 45000ms exceeded.', $payload['error']);
    }

    public function test_fetch_audit_returns_payload_on_connection_timeout(): void
    {
        Config::set('scanner.audit_service_url', 'https://browser.example.com');
        Config::set('scanner.audit_service_token', 'secret');

        Http::fake(function () {
            throw new ConnectionException(
                new ConnectException(
                    'cURL error 28: Operation timed out after 120001 milliseconds with 0 bytes received',
                    new PsrRequest('POST', 'https://browser.example.com/audit'),
                ),
            );
        });

        $payload = app(BrowserServiceClient::class)->fetchAudit('https://www.hatchards.co.uk/');

        $this->assertSame('https://www.hatchards.co.uk/', $payload['url']);
        $this->assertStringContainsString('timed out', $payload['error']);
        $this->assertSame([], $payload['violations']);
    }

    public function test_health_check_calls_health_endpoint(): void
    {
        Config::set('scanner.audit_service_url', 'https://browser.example.com');
        Config::set('scanner.audit_service_token', 'secret');

        Http::fake([
            'https://browser.example.com/health' => Http::response(['ok' => true]),
        ]);

        $result = app(BrowserServiceClient::class)->healthCheck();

        $this->assertTrue($result['ok']);
    }
}
