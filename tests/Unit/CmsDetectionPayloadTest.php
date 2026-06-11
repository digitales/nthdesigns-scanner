<?php

namespace Tests\Unit;

use App\Support\CmsDetectionPayload;
use Tests\TestCase;

class CmsDetectionPayloadTest extends TestCase
{
    public function test_from_audit_payload_returns_cms_when_valid(): void
    {
        $payload = [
            'cms' => [
                'platform' => 'wordpress',
                'confidence' => 'high',
                'signals' => [],
            ],
        ];

        $result = CmsDetectionPayload::fromAuditPayload($payload);

        $this->assertSame('wordpress', $result['platform']);
    }

    public function test_from_audit_payload_returns_null_when_missing(): void
    {
        $this->assertNull(CmsDetectionPayload::fromAuditPayload(['violations' => []]));
    }

    public function test_from_audit_payload_returns_null_when_platform_missing(): void
    {
        $this->assertNull(CmsDetectionPayload::fromAuditPayload(['cms' => ['confidence' => 'low']]));
    }

    public function test_should_not_run_fallback_when_cms_in_payload(): void
    {
        $this->assertFalse(CmsDetectionPayload::shouldRunFallback([
            'cms' => ['platform' => 'wordpress'],
        ]));
    }

    public function test_should_not_run_fallback_when_audit_reported_error(): void
    {
        config(['scanner.cms_detect_driver' => 'http']);

        $this->assertFalse(CmsDetectionPayload::shouldRunFallback([
            'url' => 'https://example.com',
            'error' => 'Navigation timeout',
            'violations' => [],
        ]));
    }

    public function test_should_not_run_fallback_when_cms_driver_is_skip(): void
    {
        config(['scanner.cms_detect_driver' => 'skip']);

        $this->assertFalse(CmsDetectionPayload::shouldRunFallback([
            'url' => 'https://example.com',
            'violations' => [],
        ]));
    }

    public function test_should_run_fallback_for_successful_audit_without_cms(): void
    {
        config(['scanner.cms_detect_driver' => 'http']);

        $this->assertTrue(CmsDetectionPayload::shouldRunFallback([
            'url' => 'https://example.com',
            'violations' => [],
            'pass_count' => 1,
        ]));
    }
}
