#!/bin/sh
set -eu

# Run from the Laravel project directory on the NAS. The MySQL container belongs
# to the sibling Flask Compose project, so its exact name is supplied explicitly.
: "${DB_CONTAINER:?Set DB_CONTAINER to the existing MySQL container name.}"

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

cleanup() {
    rm -f "$database_sql_partial" "$database_partial" "$uploads_partial"
}
trap cleanup EXIT HUP INT TERM

echo "Backing up MySQL from $DB_CONTAINER..."
docker exec "$DB_CONTAINER" sh -c \
    'exec mysqldump --single-transaction --routines --triggers --events -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
    > "$database_sql_partial"
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

echo "Backup complete:"
echo "  $database_archive"
echo "  $uploads_archive"
echo "  $checksums"
