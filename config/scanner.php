<?php

$auditDriver = env('AUDIT_DRIVER', 'playwright');
$auditServiceUrl = env('AUDIT_SERVICE_URL');

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
        $screenshotDriver = 'playwright';
    }
}

return [

    'audit_driver' => $auditDriver,

    'screenshot_driver' => $screenshotDriver,

    'audit_service_url' => $auditServiceUrl,

    'audit_service_token' => env('AUDIT_SERVICE_TOKEN'),

    'node_binary' => env('NODE_BINARY', 'node'),

    'audit_script_path' => env('AUDIT_SCRIPT_PATH') ?: base_path('scripts/audit.js'),

    'cms_detect_script_path' => env('CMS_DETECT_SCRIPT_PATH') ?: base_path('scripts/detect-cms.js'),

    'cms_detect_timeout' => (int) env('CMS_DETECT_TIMEOUT', 90),

    'lighthouse_binary' => env('LIGHTHOUSE_BINARY', 'lighthouse'),

    'playwright_browsers_path' => env('PLAYWRIGHT_BROWSERS_PATH'),

    // HTTP client timeout for Fly/browser-service /audit (page + axe + lighthouse can exceed 120s).
    'audit_timeout' => (int) env('AUDIT_TIMEOUT', 210),

    // HTTP client timeout for Fly/browser-service /screenshot (goto 45s + capture 60s + headroom).
    'screenshot_timeout' => (int) env('SCREENSHOT_TIMEOUT', 150),

    'report_booking_url' => env('REPORT_BOOKING_URL'),

    'report_expiry_days' => (int) env('REPORT_EXPIRY_DAYS', 30),

    'audit_error_detail_retention_days' => (int) env('AUDIT_ERROR_DETAIL_RETENTION_DAYS', 90),

    'reports_disk' => env('REPORTS_DISK', 'public'),

    'search_rate_limit_seconds' => (int) env('SEARCH_RATE_LIMIT_SECONDS', 30),

    'search_queue_connection' => env('SEARCH_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),

    'niche_queue_connection' => env('NICHE_QUEUE_CONNECTION', env('SCRAPING_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database'))),

    /** @deprecated Use niche_queue_connection. Kept for existing SCRAPING_QUEUE_CONNECTION env. */
    'scraping_queue_connection' => env('SCRAPING_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),

    'auditing_queue_connection' => env('AUDITING_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),

    'horizon_allowed_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('HORIZON_ALLOWED_EMAILS', ''))
    ))),

    'places_cache_enabled' => filter_var(env('PLACES_CACHE_ENABLED', true), FILTER_VALIDATE_BOOL),

    'places_cache_force' => filter_var(env('PLACES_CACHE_FORCE', false), FILTER_VALIDATE_BOOL),

    'places_details_ttl_days' => (int) env('PLACES_DETAILS_TTL_DAYS', 14),

    'website_discovery_enabled' => filter_var(env('WEBSITE_DISCOVERY_ENABLED', true), FILTER_VALIDATE_BOOL),

    /** @var string brave|google_cse */
    'website_discovery_provider' => env('WEBSITE_DISCOVERY_PROVIDER', 'brave'),

    'website_discovery_timeout_seconds' => (int) env('WEBSITE_DISCOVERY_TIMEOUT', 8),

    'website_discovery_num_results' => (int) env('WEBSITE_DISCOVERY_NUM_RESULTS', 5),

];
