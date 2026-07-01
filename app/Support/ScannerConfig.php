<?php

namespace App\Support;

use App\Jobs\AuditSiteJob;
use App\Jobs\CaptureScreenshotJob;
use App\Jobs\CombineScoresJob;
use App\Jobs\DetectCmsJob;
use App\Jobs\DirectUrlScanJob;
use App\Jobs\GenerateOutreachEmailJob;
use App\Jobs\GenerateProspectReportJob;
use App\Jobs\RegenerateOutreachForProspectJob;
use App\Jobs\ScanNicheJob;
use App\Jobs\ScorePlaceJob;
use App\Jobs\ScrapeProspectsJob;
use Illuminate\Support\Facades\Queue;

/**
 * Scanner settings derived from env() at runtime.
 *
 * config/scanner.php uses driversForConfig() during config:cache (deploy build). Cloud secrets
 * are often only available at runtime, so cached audit_driver can stay "playwright"
 * even when AUDIT_SERVICE_URL is set on workers. Call applyRuntimeOverrides() on boot.
 */
final class ScannerConfig
{
    public const PRODUCTION_BROWSER_SERVICE_URL = 'https://nth-scanner-browser.fly.dev';

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
        $auditServiceUrl = self::resolveAuditServiceUrl();
        $auditDriver = self::runtimeEnv('AUDIT_DRIVER', 'playwright');

        if ($auditServiceUrl) {
            $auditDriver = 'http';
        } elseif ($auditDriver === 'cloudflare') {
            $auditDriver = 'skip';
        }

        $screenshotDriver = self::runtimeEnv('SCREENSHOT_DRIVER');

        if (! $screenshotDriver) {
            if ($auditServiceUrl) {
                $screenshotDriver = 'http';
            } elseif (self::runtimeEnv('AUDIT_DRIVER') === 'cloudflare') {
                $screenshotDriver = 'cloudflare';
            } else {
                $screenshotDriver = 'playwright';
            }
        }

        $token = self::runtimeEnv('AUDIT_SERVICE_TOKEN');

        return [
            'audit_driver' => $auditDriver,
            'screenshot_driver' => $screenshotDriver,
            'cms_detect_driver' => self::runtimeEnv('CMS_DETECT_DRIVER') ?: $auditDriver,
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

    public static function resolveAuditServiceUrl(): ?string
    {
        $configured = self::normalizeEnvString(self::runtimeEnv('AUDIT_SERVICE_URL'));

        if ($configured !== null) {
            return $configured;
        }

        if (self::isProductionEnvironment()) {
            return self::PRODUCTION_BROWSER_SERVICE_URL;
        }

        return null;
    }

    /**
     * Read env vars at runtime without relying on env() after config:cache.
     * Laravel Cloud injects secrets into the process environment; env() often
     * returns null once config is cached and .env is not loaded.
     */
    public static function runtimeEnv(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_ENV)) {
            $value = $_ENV[$key];

            if ($value !== false && $value !== null && $value !== '') {
                return $value;
            }
        }

        if (array_key_exists($key, $_SERVER) && ! is_array($_SERVER[$key])) {
            $value = $_SERVER[$key];

            if ($value !== false && $value !== null && $value !== '') {
                return $value;
            }
        }

        $value = getenv($key);

        if ($value !== false && $value !== '') {
            return $value;
        }

        return $default;
    }

    private static function isProductionEnvironment(): bool
    {
        $runtimeEnv = self::normalizeEnvString(self::runtimeEnv('APP_ENV'));

        if ($runtimeEnv !== null) {
            return $runtimeEnv === 'production';
        }

        if (config('app.env') === 'production') {
            return true;
        }

        try {
            return app()->environment('production');
        } catch (\Throwable) {
            return false;
        }
    }

    private static function normalizeEnvString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if ((str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"'))
            || (str_starts_with($trimmed, "'") && str_ends_with($trimmed, "'"))) {
            $trimmed = substr($trimmed, 1, -1);
        }

        return $trimmed !== '' ? $trimmed : null;
    }

    public static function registerQueueRoutes(): void
    {
        Queue::route([
            DirectUrlScanJob::class => [config('scanner.search_queue_connection'), SearchQueue::NAME],
            ScrapeProspectsJob::class => [config('scanner.search_queue_connection'), SearchQueue::NAME],
            ScorePlaceJob::class => [config('scanner.search_queue_connection'), SearchQueue::NAME],
            GenerateOutreachEmailJob::class => [config('scanner.search_queue_connection'), SearchQueue::NAME],
            ScanNicheJob::class => [config('scanner.niche_queue_connection'), NicheQueue::NAME],
            AuditSiteJob::class => [config('scanner.auditing_queue_connection'), AuditingQueue::NAME],
            CaptureScreenshotJob::class => [config('scanner.auditing_queue_connection'), AuditingQueue::NAME],
            CombineScoresJob::class => [config('scanner.auditing_queue_connection'), AuditingQueue::NAME],
            DetectCmsJob::class => [config('scanner.auditing_queue_connection'), AuditingQueue::NAME],
            GenerateProspectReportJob::class => [config('scanner.auditing_queue_connection'), AuditingQueue::NAME],
            RegenerateOutreachForProspectJob::class => [config('scanner.auditing_queue_connection'), AuditingQueue::NAME],
        ]);
    }
}
