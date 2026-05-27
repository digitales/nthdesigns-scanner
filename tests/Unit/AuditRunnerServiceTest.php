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
}
