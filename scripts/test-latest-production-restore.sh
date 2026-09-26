#!/bin/sh
set -eu

# Restores the latest completed production backup into disposable resources.
# This script never connects to, writes to, or replaces the production database
# or uploads volume. Run it from the Compose project directory on the NAS.

backup_root="${BACKUP_ROOT:-/volume1/docker/backups/customerportal-laravel}"
tmp_root="${RESTORE_DRILL_TMP_ROOT:-/tmp}"

case "$backup_root" in
    /*) ;;
    *) echo "BACKUP_ROOT must be an absolute path." >&2; exit 1 ;;
esac
case "$tmp_root" in
    /*) ;;
    *) echo "RESTORE_DRILL_TMP_ROOT must be an absolute path." >&2; exit 1 ;;
esac

checksums="$(find "$backup_root" -maxdepth 1 -type f -name '*-SHA256SUMS' -print | sort | tail -n 1)"
if [ -z "$checksums" ]; then
    echo "No completed backup checksum file found in $backup_root." >&2
    exit 1
fi

backup_stamp="$(basename "${checksums%-SHA256SUMS}")"
database_archive="${checksums%-SHA256SUMS}-database.sql.gz"
uploads_archive="${checksums%-SHA256SUMS}-private-uploads.tar.gz"
run_stamp="$(date -u +%Y%m%dT%H%M%SZ)"
container_name="customer-portal-restore-drill-${run_stamp}-$$"
restore_database="customer_portal_restore_drill"
restore_password="restore-drill-${run_stamp}-$$"
report_dir="$backup_root/restore-drills"
report_file="$report_dir/${run_stamp}-${backup_stamp}.txt"
work_dir=""
table_count="not-completed"
required_table_count="not-completed"
database_check="not-completed"
upload_file_count="not-completed"
outcome="FAILED"

umask 077
mkdir -p "$tmp_root" "$report_dir"

write_report() {
    completed_at="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    operator="${SUDO_USER:-$(id -un)}"
    {
        echo "restore_drill=$outcome"
        echo "completed_at_utc=$completed_at"
        echo "operator=$operator"
        echo "backup_stamp=$backup_stamp"
        echo "checksums_file=$(basename "$checksums")"
        echo "restored_database_tables=$table_count"
        echo "validated_required_tables=$required_table_count"
        echo "database_check=$database_check"
        echo "restored_upload_files=$upload_file_count"
    } > "$report_file"
}

cleanup() {
    status=$?
    trap - EXIT HUP INT TERM

    if docker inspect "$container_name" >/dev/null 2>&1; then
        docker rm -f "$container_name" >/dev/null 2>&1 || true
    fi
    if [ -n "$work_dir" ] && [ -d "$work_dir" ]; then
        rm -rf -- "$work_dir"
    fi

    if [ "$status" -eq 0 ]; then
        outcome="PASSED"
    fi
    write_report

    if [ "$status" -ne 0 ]; then
        echo "Restore drill failed. Audit report: $report_file" >&2
    fi
    exit "$status"
}
trap cleanup EXIT HUP INT TERM

echo "Verifying backup checksums..."
(
    cd "$backup_root"
    sha256sum -c "$(basename "$checksums")"
)
gzip -t "$database_archive"
tar -tzf "$uploads_archive" >/dev/null

db_container="$(docker compose ps -q db)"
if [ -z "$db_container" ]; then
    echo "The production Compose database container is not running." >&2
    exit 1
fi
database_image="$(docker inspect --format '{{.Image}}' "$db_container")"
if [ -z "$database_image" ]; then
    echo "Could not resolve the MySQL image used by the Compose database." >&2
    exit 1
fi

echo "Starting an isolated MySQL restore container..."
docker run --detach --rm \
    --name "$container_name" \
    --network none \
    --env "MYSQL_ROOT_PASSWORD=$restore_password" \
    --env "MYSQL_DATABASE=$restore_database" \
    "$database_image" >/dev/null

ready="false"
attempt=0
while [ "$attempt" -lt 60 ]; do
    if docker exec --env "MYSQL_PWD=$restore_password" "$container_name" \
        mysqladmin ping --host=127.0.0.1 --user=root \
        --silent >/dev/null 2>&1; then
        ready="true"
        break
    fi
    attempt=$((attempt + 1))
    sleep 1
done
if [ "$ready" != "true" ]; then
    echo "Disposable MySQL did not become ready within 60 seconds." >&2
    docker logs "$container_name" >&2 || true
    exit 1
fi

echo "Restoring the database backup..."
gzip -dc "$database_archive" | docker exec -i \
    --env "MYSQL_PWD=$restore_password" "$container_name" \
    mysql --user=root "$restore_database"

table_count="$(docker exec --env "MYSQL_PWD=$restore_password" "$container_name" \
    mysql --batch --skip-column-names --user=root \
    --execute="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$restore_database';")"
if [ "$table_count" -le 0 ]; then
    echo "Restored database contains no tables." >&2
    exit 1
fi

# Read each required table directly. This proves the restored schema can open
# the application's core tables without relying on information_schema's
# aggregate metadata result.
docker exec --env "MYSQL_PWD=$restore_password" "$container_name" \
    mysql --batch --skip-column-names --user=root "$restore_database" \
    --execute="SELECT COUNT(*) FROM migrations; SELECT COUNT(*) FROM users; SELECT COUNT(*) FROM purchase_orders;" \
    >/dev/null
required_table_count=3

echo "Checking restored database tables..."
table_names="$(docker exec --env "MYSQL_PWD=$restore_password" "$container_name" \
    mysql --batch --skip-column-names --user=root \
    --execute="SELECT table_name FROM information_schema.tables WHERE table_schema = '$restore_database' ORDER BY table_name;")"
for table_name in $table_names; do
    case "$table_name" in
        *[!A-Za-z0-9_]*)
            database_check="failed"
            echo "Refusing to interpolate unexpected restored table name: $table_name" >&2
            exit 1
            ;;
    esac

    check_result="$(docker exec --env "MYSQL_PWD=$restore_password" "$container_name" \
        mysql --batch --skip-column-names --user=root "$restore_database" \
        --execute="CHECK TABLE \`$table_name\`;")"
    if ! printf '%s\n' "$check_result" | awk 'END { exit !($3 == "status" && $4 == "OK") }'; then
        database_check="failed"
        echo "CHECK TABLE failed for $table_name:" >&2
        echo "$check_result" >&2
        exit 1
    fi
done
database_check="passed"

echo "Restoring private uploads into a temporary directory..."
work_dir="$(mktemp -d "$tmp_root/customer-portal-restore-drill.XXXXXX")"
tar -xzf "$uploads_archive" -C "$work_dir"
upload_file_count="$(find "$work_dir" -type f | wc -l | tr -d ' ')"

echo "Restore drill passed:"
echo "  Backup: $backup_stamp"
echo "  Database tables restored and checked: $table_count"
echo "  Private-upload files restored: $upload_file_count"
echo "  Audit report: $report_file"
