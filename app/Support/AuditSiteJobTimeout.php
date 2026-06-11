<?php

namespace App\Support;

final class AuditSiteJobTimeout
{
    /** Headroom for scoring, screenshot storage, and queue overhead after HTTP calls. */
    public const BUFFER_SECONDS = 30;

    public static function seconds(): int
    {
        $configured = (int) config('scanner.audit_site_job_timeout');

        if ($configured > 0) {
            return $configured;
        }

        return (int) config('scanner.audit_timeout')
            + (int) config('scanner.cms_detect_timeout')
            + self::BUFFER_SECONDS;
    }
}
