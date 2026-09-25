#!/bin/sh
set -eu

health_url="${HEALTH_URL:-https://customerportal.theomeds.com/health/ready}"

echo "Checking container state..."
for service in app proxy reverb broadcast-worker notification-worker monitoring-worker scheduler db redis; do
    container_id="$(sudo docker compose ps -q "$service")"
    if [ -z "$container_id" ]; then
        echo "No container found for required service: $service" >&2
        exit 1
    fi

    state="$(sudo docker inspect --format '{{.State.Status}}' "$container_id")"
    if [ "$state" != "running" ]; then
        echo "Required service $service is $state, not running." >&2
        exit 1
    fi
done

echo "Running application smoke tests..."
sudo docker compose exec -T app php artisan system:smoke-test

echo "Checking public readiness endpoint..."
attempt=1
while [ "$attempt" -le 6 ]; do
    if curl --fail --silent --show-error --max-time 15 "$health_url" >/dev/null; then
        echo "Deployment verified at ${DEPLOYED_COMMIT:-unknown}: $health_url"
        exit 0
    fi

    if [ "$attempt" -lt 6 ]; then
        sleep 5
    fi
    attempt="$((attempt + 1))"
done

echo "Public readiness did not recover after six attempts: $health_url" >&2
exit 1
