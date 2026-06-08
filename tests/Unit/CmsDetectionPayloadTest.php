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
}
