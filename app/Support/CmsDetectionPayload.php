<?php

namespace App\Support;

final class CmsDetectionPayload
{
    /**
     * @param  array<string, mixed>  $auditPayload
     * @return array<string, mixed>|null
     */
    public static function fromAuditPayload(array $auditPayload): ?array
    {
        $cms = $auditPayload['cms'] ?? null;

        if (! is_array($cms) || ! isset($cms['platform'])) {
            return null;
        }

        return $cms;
    }

    /**
     * Whether to POST /detect-cms after an audit when the payload omitted cms.
     * Skips redundant browser sessions when the audit already failed or CMS is disabled.
     */
    public static function shouldRunFallback(array $auditPayload): bool
    {
        if (self::fromAuditPayload($auditPayload) !== null) {
            return false;
        }

        if (! empty($auditPayload['error'])) {
            return false;
        }

        if (config('scanner.cms_detect_driver') === 'skip') {
            return false;
        }

        return true;
    }
}
