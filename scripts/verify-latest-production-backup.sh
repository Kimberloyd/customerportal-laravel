#!/bin/sh
set -eu

backup_root="${BACKUP_ROOT:-/volume1/docker/backups/customerportal-laravel}"
max_age_hours="${BACKUP_MAX_AGE_HOURS:-26}"

case "$backup_root" in
    /*) ;;
    *) echo "BACKUP_ROOT must be an absolute path." >&2; exit 1 ;;
esac

checksums="$(find "$backup_root" -maxdepth 1 -type f -name '*-SHA256SUMS' -print | sort | tail -n 1)"
if [ -z "$checksums" ]; then
    echo "No completed backup checksum file found in $backup_root." >&2
    exit 1
fi

now="$(date +%s)"
modified="$(date -r "$checksums" +%s)"
age_hours="$(( (now - modified) / 3600 ))"
if [ "$age_hours" -gt "$max_age_hours" ]; then
    echo "Latest backup is ${age_hours} hours old (maximum ${max_age_hours})." >&2
    exit 1
fi

(
    cd "$backup_root"
    sha256sum -c "$(basename "$checksums")"
)

database_archive="${checksums%-SHA256SUMS}-database.sql.gz"
uploads_archive="${checksums%-SHA256SUMS}-private-uploads.tar.gz"
gzip -t "$database_archive"
tar -tzf "$uploads_archive" >/dev/null

echo "Latest backup is current and verified: $checksums"
