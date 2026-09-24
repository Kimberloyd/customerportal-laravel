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
# Requires the docker-rollout CLI plugin (~/.docker/cli-plugins/docker-rollout)
# and that app/proxy define no `ports:`/`container_name` (see docker-compose.yml).
set -e

echo "==> Pulling latest commit"
git pull origin master

echo "==> Building app + proxy images"
sudo docker compose build app proxy

echo "==> Rolling out app (zero-downtime)"
sudo docker rollout app

echo "==> Running migrations (against the new app image)"
sudo docker compose exec -T app php artisan migrate --force

echo "==> Clearing cached config/routes/views"
sudo docker compose exec -T app php artisan optimize:clear

echo "==> Rolling out proxy (zero-downtime)"
sudo docker rollout proxy

echo "==> Restarting reverb/broadcast-worker/scheduler"
sudo docker compose up -d reverb broadcast-worker scheduler

echo "==> Done. Now running:"
git log -1 --oneline
