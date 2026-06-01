<?php

namespace Tests\Unit;

use App\Services\CmsDetectionRunnerService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CmsDetectionRunnerServiceTest extends TestCase
{
    public function test_run_uses_browser_service_when_audit_driver_is_http(): void
    {
        Config::set('scanner.audit_driver', 'http');
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
            ], 200),
        ]);

        $result = app(CmsDetectionRunnerService::class)->run('https://example.com');

        $this->assertSame('wordpress', $result['platform']);
        Http::assertSent(fn ($request) => $request->url() === 'https://browser.example.com/detect-cms');
    }
}
