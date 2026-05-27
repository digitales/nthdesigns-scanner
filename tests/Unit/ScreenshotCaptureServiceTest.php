<?php

namespace Tests\Unit;

use App\Services\ScreenshotCaptureService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScreenshotCaptureServiceTest extends TestCase
{
    public function test_cloudflare_driver_captures_desktop_png(): void
    {
        Config::set('scanner.screenshot_driver', 'cloudflare');
        Config::set('services.cloudflare.api_token', 'test-token');
        Config::set('services.cloudflare.account_id', 'account-123');

        Http::fake([
            'https://api.cloudflare.com/client/v4/accounts/account-123/browser-rendering/screenshot' => Http::response(
                'png-data',
                200,
                ['Content-Type' => 'image/png'],
            ),
        ]);

        $localDir = storage_path('app/temp/screenshot-service-test');
        @mkdir($localDir, 0755, true);

        try {
            $path = app(ScreenshotCaptureService::class)->captureDesktop('https://example.com', $localDir);

            $this->assertSame($localDir.'/desktop.png', $path);
            $this->assertSame('png-data', file_get_contents($path));
        } finally {
            @unlink($localDir.'/desktop.png');
            @rmdir($localDir);
        }
    }

    public function test_http_driver_captures_desktop_png(): void
    {
        Config::set('scanner.screenshot_driver', 'http');
        Config::set('scanner.audit_service_url', 'https://browser.example.com');
        Config::set('scanner.audit_service_token', 'secret');

        $encoded = base64_encode('png-from-fly');

        Http::fake([
            'https://browser.example.com/screenshot' => Http::response([
                'desktop' => 'desktop.png',
                'content_base64' => $encoded,
            ]),
        ]);

        $localDir = storage_path('app/temp/screenshot-http-test');
        @mkdir($localDir, 0755, true);

        try {
            $path = app(ScreenshotCaptureService::class)->captureDesktop('https://example.com', $localDir);

            $this->assertSame($localDir.'/desktop.png', $path);
            $this->assertSame('png-from-fly', file_get_contents($path));
        } finally {
            @unlink($localDir.'/desktop.png');
            @rmdir($localDir);
        }
    }
}
