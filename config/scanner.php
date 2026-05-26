<?php

return [

    'node_binary' => env('NODE_BINARY', 'node'),

    'audit_script_path' => env('AUDIT_SCRIPT_PATH') ?: base_path('scripts/audit.js'),

    'lighthouse_binary' => env('LIGHTHOUSE_BINARY', 'lighthouse'),

    'audit_timeout' => (int) env('AUDIT_TIMEOUT', 120),

    'report_booking_url' => env('REPORT_BOOKING_URL'),

    'report_expiry_days' => (int) env('REPORT_EXPIRY_DAYS', 30),

];
