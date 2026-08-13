<?php

// Mirrors the Flask app's LOGIN_LOCKOUT_THRESHOLD / LOGIN_LOCKOUT_WINDOW_MINUTES
// (config.py) -- same env var names, same defaults, backed by the same
// shared login_attempts table.
return [
    'threshold' => (int) env('LOGIN_LOCKOUT_THRESHOLD', 5),
    'window_minutes' => (int) env('LOGIN_LOCKOUT_WINDOW_MINUTES', 15),
];
