<?php

namespace App\Support;

/**
 * Scanner settings derived from env() at runtime.
 *
 * config/scanner.php uses driversForConfig() during config:cache (deploy build). Cloud secrets
 * are often only available at runtime, so cached audit_driver can stay "playwright"
 * even when AUDIT_SERVICE_URL is set on workers. Call applyRuntimeOverrides() on boot.
 */
final class ScannerConfig
{
    /**
     * Resolve audit/screenshot/cms drivers from env for config file and runtime overrides.
     *
     * @return array{
     *     audit_driver: string,
     *     screenshot_driver: string,
     *     cms_detect_driver: string,
     *     audit_service_url: string|null,
     *     audit_service_token: string|null
     * }
     */
    public static function driversForConfig(): array
    {
        $auditServiceUrl = env('AUDIT_SERVICE_URL') ?: null;
        $auditDriver = env('AUDIT_DRIVER', 'playwright');

        if ($auditServiceUrl) {
            $auditDriver = 'http';
        } elseif ($auditDriver === 'cloudflare') {
            $auditDriver = 'skip';
        }

        $screenshotDriver = env('SCREENSHOT_DRIVER');

        if (! $screenshotDriver) {
            if ($auditServiceUrl) {
                $screenshotDriver = 'http';
            } elseif (env('AUDIT_DRIVER') === 'cloudflare') {
                $screenshotDriver = 'cloudflare';
            } else {
                $screenshotDriver = 'playwright';
            }
        }

        $token = env('AUDIT_SERVICE_TOKEN');

        return [
            'audit_driver' => $auditDriver,
            'screenshot_driver' => $screenshotDriver,
            'cms_detect_driver' => env('CMS_DETECT_DRIVER') ?: $auditDriver,
            'audit_service_url' => $auditServiceUrl,
            'audit_service_token' => ($token !== null && $token !== '') ? $token : null,
        ];
    }

    public static function applyRuntimeOverrides(): void
    {
        $drivers = self::driversForConfig();

        $overrides = [
            'scanner.audit_driver' => $drivers['audit_driver'],
            'scanner.screenshot_driver' => $drivers['screenshot_driver'],
            'scanner.cms_detect_driver' => $drivers['cms_detect_driver'],
        ];

        if ($drivers['audit_service_url']) {
            $overrides['scanner.audit_service_url'] = $drivers['audit_service_url'];

            if ($drivers['audit_service_token'] !== null) {
                $overrides['scanner.audit_service_token'] = $drivers['audit_service_token'];
            }
        }

        config($overrides);
    }
}
