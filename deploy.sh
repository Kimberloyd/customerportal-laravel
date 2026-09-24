#!/bin/sh
# Deploys the currently-pushed commit on origin/master to this NAS.
# Run from /volume1/docker/customerportal-laravel on the NAS itself.
#
# Wraps the sequence that's been done by hand all session (and the exact
# steps that get forgotten -- the proxy image not being rebuilt is what
# caused the stale-asset 404s earlier): pull, rebuild both images that
# bundle app code, recreate every container running off them, migrate,
# clear cached config/routes.
set -e

echo "==> Pulling latest commit"
git pull origin master

echo "==> Building app + proxy images"
sudo docker compose build app proxy

echo "==> Recreating containers"
sudo docker compose up -d app reverb broadcast-worker scheduler proxy

echo "==> Running migrations"
sudo docker compose exec -T app php artisan migrate --force

echo "==> Clearing cached config/routes/views"
sudo docker compose exec -T app php artisan optimize:clear

echo "==> Done. Now running:"
git log -1 --oneline
