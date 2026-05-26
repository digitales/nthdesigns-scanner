<?php

namespace Tests\Unit;

use App\Services\CloudflareBrowserService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CloudflareBrowserServiceTest extends TestCase
{
    public function test_capture_screenshot_writes_png_from_cloudflare_api(): void
    {
        Config::set('services.cloudflare.api_token', 'test-token');
        Config::set('services.cloudflare.account_id', 'account-123');

        Http::fake([
            'https://api.cloudflare.com/client/v4/accounts/account-123/browser-rendering/screenshot' => Http::response(
                'fake-png-bytes',
                200,
                ['Content-Type' => 'image/png'],
            ),
        ]);

        $outputPath = storage_path('app/temp/cloudflare-test.png');
        @mkdir(dirname($outputPath), 0755, true);

        try {
            app(CloudflareBrowserService::class)->captureScreenshot('https://example.com', $outputPath);

            $this->assertFileExists($outputPath);
            $this->assertSame('fake-png-bytes', file_get_contents($outputPath));
        } finally {
            @unlink($outputPath);
        }
    }

    public function test_is_configured_requires_token_and_account_id(): void
    {
        Config::set('services.cloudflare.api_token', '');
        Config::set('services.cloudflare.account_id', '');

        $this->assertFalse(app(CloudflareBrowserService::class)->isConfigured());

        Config::set('services.cloudflare.api_token', 'token');
        Config::set('services.cloudflare.account_id', 'acct');

        $this->assertTrue(app(CloudflareBrowserService::class)->isConfigured());
    }
}
