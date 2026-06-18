<?php

return [
    'min_size' => (int) env('WARMUP_POOL_MIN_SIZE', 10),
    'alert_size' => (int) env('WARMUP_POOL_ALERT_SIZE', 50),
    'bounce_count_threshold' => 5,
    'bounce_rate_threshold' => 0.30,
    'bounce_rate_min_sends' => 10,
    'lookback_days' => 7,
];
