#!/bin/sh
# Deploys the currently-pushed commit on origin/master to this NAS.
# Run from /volume1/docker/customerportal-laravel on the NAS itself.
#
# app and proxy are the two services on the actual request path (nginx ->
# php-fpm), so they're rolled out with `docker rollout` instead of a plain
# `up -d`: it starts a second, new-image instance alongside the running
# one, waits for its healthcheck to pass, then removes the old instance --
# no window where nothing is listening, which a plain recreate always has
# (that's what caused the 502s during every deploy before this). reverb/
# broadcast-worker/scheduler don't sit on that path (a live websocket
# client reconnects on its own), so they stay on the simpler plain
# restart.
#
# The proxy rolls out BEFORE the app. It's the container that serves the
# hashed JS/CSS files, and a page from the app names files by hash: if the
# app switched first, its new pages would ask the old proxy for files it
# doesn't have yet, and Cloudflare/browsers would remember those 404s for
# hours. The new proxy image carries the previous release's files as well as
# its own (see docker/nginx/Dockerfile), so during the switch both the old
# app's pages and the new app's pages find everything they reference.
#
# Requires the docker-rollout CLI plugin (~/.docker/cli-plugins/docker-rollout)
# and that app/proxy define no `ports:`/`container_name` (see docker-compose.yml).
set -e

echo "==> Pulling latest commit"
git pull --ff-only origin master
deployed_commit="$(git rev-parse HEAD)"
if [ -n "${DEPLOY_COMMIT:-}" ] && [ "$deployed_commit" != "$DEPLOY_COMMIT" ]; then
    echo "Expected commit $DEPLOY_COMMIT but master resolved to $deployed_commit; refusing deployment." >&2
    exit 1
fi

echo "==> Recording the proxy image being replaced"
previous_proxy_image=""
running_proxy="$(sudo docker compose ps -q proxy | head -n 1)"
if [ -n "$running_proxy" ]; then
    previous_proxy_id="$(sudo docker inspect --format '{{.Image}}' "$running_proxy")"
    sudo docker tag "$previous_proxy_id" customerportal-laravel-proxy:previous
    previous_proxy_image="customerportal-laravel-proxy:previous"
fi

echo "==> Building app + proxy images"
sudo env PREVIOUS_PROXY_IMAGE="$previous_proxy_image" docker compose build app proxy
sudo docker tag customerportal-laravel-app:latest "customerportal-laravel-app:$deployed_commit"
sudo docker tag customerportal-laravel-proxy:latest "customerportal-laravel-proxy:$deployed_commit"

echo "==> Rolling out proxy (zero-downtime)"
sudo docker rollout proxy

echo "==> Rolling out app (zero-downtime)"
sudo docker rollout app

echo "==> Running migrations (against the new app image)"
sudo docker compose exec -T app php artisan migrate --force

echo "==> Clearing cached config/routes/views"
sudo docker compose exec -T app php artisan optimize:clear

echo "==> Restarting reverb/queue workers/scheduler"
sudo docker compose up -d reverb broadcast-worker notification-worker monitoring-worker scheduler

echo "==> Running post-deploy checks"
DEPLOYED_COMMIT="$deployed_commit" sh scripts/post-deploy-check.sh

echo "==> Done. Now running:"
git log -1 --oneline
