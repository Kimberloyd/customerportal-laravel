#!/bin/sh
set -e

# The tmpfs mounts for these paths (docker-compose.yml) are fresh,
# root-owned filesystems every time the container starts -- the
# build-time chown in the Dockerfile only ever applied to the image
# layer underneath them, not to what's actually mounted at runtime.
# Re-chown here, as root, before dropping to the unprivileged `app`
# user to actually run the command.
for dir in \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache
do
    if [ -d "$dir" ]; then
        chown app:app "$dir"
    fi
done

# Stay root to exec php-fpm itself -- its master process must open
# error_log (/proc/self/fd/2, php-fpm's default) as root, since that's
# a real open() on Docker's stderr pipe, which is root-owned at
# creation time regardless of who the entrypoint later becomes; a
# non-root master fails with "Permission denied" trying to reopen it.
# Worker processes (the ones that actually run PHP/Laravel code) still
# drop to the unprivileged `app` user, via the pool's user/group
# directives (docker/app/www.conf) -- same division of privilege the
# proxy service's nginx master/worker split already uses. All other
# commands (notably the scheduler) drop privileges here.
if [ "$1" = "php-fpm" ]; then
    exec "$@"
fi

exec su-exec app "$@"
