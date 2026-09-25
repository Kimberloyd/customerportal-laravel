#!/bin/sh
set -eu

# Run from the Laravel project directory on the NAS. By default the database is
# the `db` service in this Compose project. DB_CONTAINER remains supported for
# older deployments that point at an explicitly named external container.

backup_root="${BACKUP_ROOT:-/volume1/docker/backups/customerportal-laravel}"
case "$backup_root" in
    /*) ;;
    *) echo "BACKUP_ROOT must be an absolute path." >&2; exit 1 ;;
esac

umask 077
mkdir -p "$backup_root"

stamp="$(date -u +%Y%m%dT%H%M%SZ)"
database_sql_partial="$backup_root/$stamp-database.sql.partial"
database_partial="$backup_root/$stamp-database.sql.gz.partial"
database_archive="$backup_root/$stamp-database.sql.gz"
uploads_partial="$backup_root/$stamp-private-uploads.tar.gz.partial"
uploads_archive="$backup_root/$stamp-private-uploads.tar.gz"
checksums="$backup_root/$stamp-SHA256SUMS"
metadata="$backup_root/$stamp-METADATA"

cleanup() {
    rm -f "$database_sql_partial" "$database_partial" "$uploads_partial"
}
trap cleanup EXIT HUP INT TERM

if [ -n "${DB_CONTAINER:-}" ]; then
    echo "Backing up MySQL container $DB_CONTAINER..."
    docker exec "$DB_CONTAINER" sh -c \
        'exec mysqldump --single-transaction --routines --triggers --events -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
        > "$database_sql_partial"
else
    echo "Backing up MySQL Compose service db..."
    docker compose exec -T db sh -c \
        'exec mysqldump --single-transaction --routines --triggers --events -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
        > "$database_sql_partial"
fi
test -s "$database_sql_partial"
gzip -9 < "$database_sql_partial" > "$database_partial"
rm -f "$database_sql_partial"
gzip -t "$database_partial"
test -s "$database_partial"
mv "$database_partial" "$database_archive"

echo "Backing up private uploads..."
docker compose exec -T app tar -C /app/storage/app/private -czf - . > "$uploads_partial"
tar -tzf "$uploads_partial" >/dev/null
test -s "$uploads_partial"
mv "$uploads_partial" "$uploads_archive"

(
    cd "$backup_root"
    sha256sum "$(basename "$database_archive")" "$(basename "$uploads_archive")" > "$(basename "$checksums")"
)

{
    echo "created_at_utc=$stamp"
    echo "git_commit=$(git rev-parse HEAD 2>/dev/null || echo unknown)"
    echo "database_archive=$(basename "$database_archive")"
    echo "uploads_archive=$(basename "$uploads_archive")"
} > "$metadata"

# Verify exactly what was written before declaring success. This detects a
# truncated archive or checksum mismatch immediately, while the source system
# is still available to retry the backup.
(
    cd "$backup_root"
    sha256sum -c "$(basename "$checksums")"
)
gzip -t "$database_archive"
tar -tzf "$uploads_archive" >/dev/null

echo "Backup complete:"
echo "  $database_archive"
echo "  $uploads_archive"
echo "  $checksums"
echo "  $metadata"
