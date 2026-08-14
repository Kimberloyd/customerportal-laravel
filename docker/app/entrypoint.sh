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

exec su-exec app "$@"
