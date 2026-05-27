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

    'lighthouse_binary' => env('LIGHTHOUSE_BINARY', 'lighthouse'),

    'playwright_browsers_path' => env('PLAYWRIGHT_BROWSERS_PATH'),

    // HTTP client timeout for Fly/browser-service /audit (page + axe + lighthouse can exceed 120s).
    'audit_timeout' => (int) env('AUDIT_TIMEOUT', 180),

    // HTTP client timeout for Fly/browser-service /screenshot (goto + 60s capture).
    'screenshot_timeout' => (int) env('SCREENSHOT_TIMEOUT', 120),

    'report_booking_url' => env('REPORT_BOOKING_URL'),

    'report_expiry_days' => (int) env('REPORT_EXPIRY_DAYS', 30),

    'reports_disk' => env('REPORTS_DISK', 'public'),

    'search_rate_limit_seconds' => (int) env('SEARCH_RATE_LIMIT_SECONDS', 30),

    'scraping_queue_connection' => env('SCRAPING_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),

    'auditing_queue_connection' => env('AUDITING_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),

    'horizon_allowed_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('HORIZON_ALLOWED_EMAILS', ''))
    ))),

];
