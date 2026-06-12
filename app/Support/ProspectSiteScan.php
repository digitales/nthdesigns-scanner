<?php

namespace App\Support;

use App\Enums\AuditStatus;
use App\Models\Prospect;

final class ProspectSiteScan
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public static function preflightFailed(?array $payload): bool
    {
        return is_array($payload) && ! empty($payload['preflight_failed']);
    }

    public static function siteUnreachable(Prospect $prospect): bool
    {
        return $prospect->audit_status === AuditStatus::Failed
            && self::preflightFailed($prospect->raw_a11y_payload);
    }
}
