<?php

namespace Tests\Unit;

use App\Support\AuditSiteJobTimeout;
use Tests\TestCase;

class AuditSiteJobTimeoutTest extends TestCase
{
    public function test_defaults_to_audit_plus_cms_plus_buffer(): void
    {
        config([
            'scanner.audit_site_job_timeout' => 0,
            'scanner.audit_timeout' => 210,
            'scanner.cms_detect_timeout' => 90,
        ]);

        $this->assertSame(330, AuditSiteJobTimeout::seconds());
    }

    public function test_respects_explicit_override(): void
    {
        config([
            'scanner.audit_site_job_timeout' => 400,
            'scanner.audit_timeout' => 210,
            'scanner.cms_detect_timeout' => 90,
        ]);

        $this->assertSame(400, AuditSiteJobTimeout::seconds());
    }
}
