<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Ads API — CPC benchmark lookup
    |--------------------------------------------------------------------------
    |
    | Fetches local keyword CPC via KeywordPlanIdeaService (REST). Requires a
    | Google Ads account, developer token, and OAuth refresh token. See
    | docs/integrations/google-ads-cpc.md for setup.
    |
    */

    'enabled' => filter_var(env('GOOGLE_ADS_ENABLED', false), FILTER_VALIDATE_BOOL),

    /** Dispatch FetchSearchCpcJob when a search is created without manual CPC. */
    'auto_fetch_on_search' => filter_var(env('GOOGLE_ADS_CPC_AUTO_FETCH', false), FILTER_VALIDATE_BOOL),

    'api_version' => env('GOOGLE_ADS_API_VERSION', 'v18'),

    'developer_token' => env('GOOGLE_ADS_DEVELOPER_TOKEN'),

    /** Customer ID to query (no dashes). Account currency should match your market (GBP for UK). */
    'customer_id' => preg_replace('/\D/', '', (string) env('GOOGLE_ADS_CUSTOMER_ID', '')),

    /** Optional MCC / manager account ID (no dashes) when using a manager login. */
    'login_customer_id' => preg_replace('/\D/', '', (string) env('GOOGLE_ADS_LOGIN_CUSTOMER_ID', '')),

    'oauth' => [
        'client_id' => env('GOOGLE_ADS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_ADS_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_ADS_REFRESH_TOKEN'),
    ],

    /** languageConstants/1000 = English */
    'language_constant' => env('GOOGLE_ADS_LANGUAGE_CONSTANT', 'languageConstants/1000'),

    'keyword_plan_network' => env('GOOGLE_ADS_KEYWORD_NETWORK', 'GOOGLE_SEARCH'),

    /** Max seed keywords sent per lookup. */
    'max_seed_keywords' => (int) env('GOOGLE_ADS_MAX_SEED_KEYWORDS', 5),

    /** Max idea results to read when aggregating CPC. */
    'page_size' => (int) env('GOOGLE_ADS_KEYWORD_PAGE_SIZE', 50),

    /**
     * Optional static geo target IDs (geoTargetConstants/{id}) keyed by "city|country".
     * When missing, the API suggestGeoTargetConstants endpoint is used.
     */
    'geo_targets' => [
        // 'birmingham|GB' => 'geoTargetConstants/9041139',
    ],

];
