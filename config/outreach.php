<?php

return [
    'soft_daily_cap' => (int) env('OUTREACH_SOFT_DAILY_CAP', 20),
    'smtp_timeout_seconds' => (int) env('OUTREACH_SMTP_TIMEOUT', 15),
];
