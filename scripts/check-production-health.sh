#!/bin/sh
set -eu

health_url="${HEALTH_URL:-https://customerportal.theomeds.com/health/ready}"
alert_webhook_url="${ALERT_WEBHOOK_URL:-}"
state_dir="${HEALTH_STATE_DIR:-/volume1/docker/customerportal-laravel-health}"
state_file="$state_dir/status"

mkdir -p "$state_dir"

if response="$(curl --fail --silent --show-error --max-time 15 "$health_url" 2>&1)"; then
    current="healthy"
    message="Customer Portal health check recovered: $health_url"
else
    current="unhealthy"
    message="Customer Portal health check failed: $health_url — $response"
fi

previous="unknown"
if [ -f "$state_file" ]; then
    previous="$(cat "$state_file")"
fi
printf '%s\n' "$current" > "$state_file"

# Alert only when state changes, preventing a webhook flood during an outage.
if [ "$current" != "$previous" ] && [ -n "$alert_webhook_url" ]; then
    escaped="$(printf '%s' "$message" | sed 's/\\/\\\\/g; s/"/\\"/g')"
    curl --fail --silent --show-error --max-time 15 \
        -H 'Content-Type: application/json' \
        --data "{\"text\":\"$escaped\"}" \
        "$alert_webhook_url" >/dev/null
fi

if [ "$current" = "unhealthy" ]; then
    echo "$message" >&2
    exit 1
fi

echo "$message"
