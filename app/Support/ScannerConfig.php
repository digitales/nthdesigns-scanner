<?php

namespace App\Support;

/**
 * Scanner settings derived from env() at runtime.
 *
 * config/scanner.php uses env() during config:cache (deploy build). Cloud secrets
 * are often only available at runtime, so cached audit_driver can stay "playwright"
 * even when AUDIT_SERVICE_URL is set on workers. Call applyRuntimeOverrides() on boot.
 */
final class ScannerConfig
{
    public static function applyRuntimeOverrides(): void
    {
        $auditServiceUrl = env('AUDIT_SERVICE_URL');
        $auditDriver = env('AUDIT_DRIVER', (string) config('scanner.audit_driver', 'playwright'));

        if ($auditServiceUrl) {
            $auditDriver = 'http';
        } elseif ($auditDriver === 'cloudflare') {
            $auditDriver = 'skip';
        }

        $screenshotDriver = env('SCREENSHOT_DRIVER');

        if (!$screenshotDriver) {
            if ($auditServiceUrl) {
                $screenshotDriver = 'http';
            } elseif (env('AUDIT_DRIVER') === 'cloudflare') {
                $screenshotDriver = 'cloudflare';
            } else {
                $screenshotDriver = (string) config('scanner.screenshot_driver', 'playwright');
            }
        }

        $overrides = [
            'scanner.audit_driver' => $auditDriver,
            'scanner.screenshot_driver' => $screenshotDriver,
        ];

        if ($auditServiceUrl) {
            $overrides['scanner.audit_service_url'] = $auditServiceUrl;

            $token = env('AUDIT_SERVICE_TOKEN');
            if ($token !== null && $token !== '') {
                $overrides['scanner.audit_service_token'] = $token;
            }
        }

        config($overrides);
    }
}
