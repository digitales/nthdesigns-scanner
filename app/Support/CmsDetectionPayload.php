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
}
