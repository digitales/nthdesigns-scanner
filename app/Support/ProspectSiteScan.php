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

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public static function payloadErrorMessage(?array $payload): ?string
    {
        if (! is_array($payload) || empty($payload['error'])) {
            return null;
        }

        $message = trim((string) $payload['error']);

        return $message !== '' ? $message : null;
    }

    public static function isAuditServiceErrorMessage(string $message): bool
    {
        $message = trim($message);

        if ($message === '') {
            return false;
        }

        $serviceUrl = (string) config('scanner.audit_service_url', '');

        if ($serviceUrl !== '') {
            $host = parse_url($serviceUrl, PHP_URL_HOST);

            if (is_string($host) && $host !== '' && str_contains($message, $host)) {
                return true;
            }
        }

        return str_contains(strtolower($message), 'audit service failed:');
    }

    /**
     * @return array{kind: string, message: string}|null
     */
    public static function auditIssue(Prospect $prospect): ?array
    {
        $payload = $prospect->raw_a11y_payload;
        $message = self::payloadErrorMessage($payload);

        if ($message === null) {
            return null;
        }

        if ($prospect->audit_status === AuditStatus::Failed && self::preflightFailed($payload)) {
            return ['kind' => 'site_unreachable', 'message' => $message];
        }

        if (self::isAuditServiceErrorMessage($message)) {
            return ['kind' => 'audit_service', 'message' => $message];
        }

        return ['kind' => 'site_load', 'message' => $message];
    }

    /**
     * @return array{audit_issue_kind: string|null, site_load_error: string|null, audit_service_error: string|null}
     */
    public static function auditIssueFields(Prospect $prospect): array
    {
        $issue = self::auditIssue($prospect);
        $kind = $issue['kind'] ?? null;

        return [
            'audit_issue_kind' => $kind,
            'site_load_error' => $kind === 'site_load' ? $issue['message'] : null,
            'audit_service_error' => $kind === 'audit_service' ? $issue['message'] : null,
        ];
    }
}
