<?php

return [
    'heartbeat_max_age_seconds' => (int) env('RELIABILITY_HEARTBEAT_MAX_AGE_SECONDS', 180),
    'check_redis' => env('RELIABILITY_CHECK_REDIS', true),
];
