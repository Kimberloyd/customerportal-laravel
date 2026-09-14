<?php

// Named after the legacy Flask app's LOGIN_LOCKOUT_THRESHOLD /
// LOGIN_LOCKOUT_WINDOW_MINUTES (config.py) for familiarity -- same env
// var names, same defaults. The login_attempts table itself was never
// actually shared with Flask (see docs/flask-coupling.md); it's always
// been Laravel-only.
return [
    'threshold' => (int) env('LOGIN_LOCKOUT_THRESHOLD', 5),
    'window_minutes' => (int) env('LOGIN_LOCKOUT_WINDOW_MINUTES', 15),
];
