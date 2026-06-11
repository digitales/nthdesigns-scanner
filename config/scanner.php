<?php

use App\Support\ScannerConfig;

$drivers = ScannerConfig::driversForConfig();

return [

    'audit_driver' => $drivers['audit_driver'],

    /** @var string playwright|http|skip — defaults to audit_driver when unset */
    'cms_detect_driver' => $drivers['cms_detect_driver'],

    'screenshot_driver' => $drivers['screenshot_driver'],

    'audit_service_url' => $drivers['audit_service_url'],

    'audit_service_token' => $drivers['audit_service_token'],

    'node_binary' => env('NODE_BINARY', 'node'),

    'audit_script_path' => env('AUDIT_SCRIPT_PATH') ?: base_path('scripts/audit.js'),

    'cms_detect_script_path' => env('CMS_DETECT_SCRIPT_PATH') ?: base_path('scripts/detect-cms.js'),

    'cms_detect_timeout' => (int) env('CMS_DETECT_TIMEOUT', 90),

    'lighthouse_binary' => env('LIGHTHOUSE_BINARY', 'lighthouse'),

    'playwright_browsers_path' => env('PLAYWRIGHT_BROWSERS_PATH'),

    // HTTP client timeout for Fly/browser-service /audit (page + axe + lighthouse can exceed 120s).
    'audit_timeout' => (int) env('AUDIT_TIMEOUT', 210),

    /**
     * Queue worker timeout for AuditSiteJob. When 0, defaults to audit_timeout + cms_detect_timeout + 30s.
     * Managed queue visibility and worker --timeout must exceed this value.
     */
    'audit_site_job_timeout' => (int) env('AUDIT_SITE_JOB_TIMEOUT', 0),

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

    /** Seconds to wait between Google Places pagination requests (API requirement; 0 in tests). */
    'places_pagination_delay_seconds' => (int) env('PLACES_PAGINATION_DELAY_SECONDS', 2),

    /** Poll interval for MCP streamable progress watches. */
    'mcp_progress_poll_seconds' => (int) env('MCP_PROGRESS_POLL_SECONDS', 2),

    /** Max outreach queue rows loaded per request (index + generate). */
    'outreach_queue_max' => (int) env('OUTREACH_QUEUE_MAX', 200),

    'api_quota' => [
        'enforcement' => filter_var(env('API_QUOTA_ENFORCEMENT', true), FILTER_VALIDATE_BOOL),
        'warning_percent' => (int) env('API_QUOTA_WARNING_PERCENT', 80),
        'limits' => [
            'google_places' => [
                'text_search' => [
                    'daily' => (int) env('API_QUOTA_PLACES_TEXT_SEARCH_DAILY', 500),
                    'monthly' => (int) env('API_QUOTA_PLACES_TEXT_SEARCH_MONTHLY', 10000),
                ],
                'place_details' => [
                    'daily' => (int) env('API_QUOTA_PLACES_PLACE_DETAILS_DAILY', 200),
                    'monthly' => (int) env('API_QUOTA_PLACES_PLACE_DETAILS_MONTHLY', 2000),
                ],
            ],
            'brave' => [
                'web_search' => [
                    'daily' => (int) env('API_QUOTA_BRAVE_WEB_SEARCH_DAILY', 100),
                    'monthly' => (int) env('API_QUOTA_BRAVE_WEB_SEARCH_MONTHLY', 3000),
                ],
            ],
        ],
        'cost_pence' => [
            'google_places' => [
                'text_search' => (float) env('API_COST_PLACES_TEXT_SEARCH_PENCE', 0.1),
                'place_details' => (float) env('API_COST_PLACES_PLACE_DETAILS_PENCE', 2.0),
            ],
            'brave' => [
                'web_search' => (float) env('API_COST_BRAVE_WEB_SEARCH_PENCE', 0.5),
            ],
        ],
    ],

];
