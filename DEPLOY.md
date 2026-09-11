# Deploying to the NAS (private)

This app is meant to run alongside the existing Flask app
(`/volume1/docker/customerportal`) on the same NAS, sharing the same
MySQL database and data. It is **not** exposed publicly — no
Cloudflare tunnel, no domain — just reachable on the NAS's local
network at `http://<nas-ip>:${HOST_PORT}` (default port `8090`).

These commands are meant to be run **on the NAS terminal**, in
`/volume1/docker/customerportal-laravel` (this directory, once the
latest commit has synced there).

## 1. Confirm the Flask network name

```bash
docker network ls
```

Look for a network that looks like `customerportal_default` (or
similar — it's whatever Compose auto-named the Flask stack's default
network when it was first brought up). If it's *not* exactly
`customerportal_default`, set `FLASK_NETWORK_NAME=<the real name>` in
`.env` in step 2 below — everything else in this guide stays the same.

## 2. Create `.env`

```bash
cp .env.production.example .env
```

Then edit `.env` and fill in:

- `APP_KEY` — generate one:
  ```bash
  docker run --rm -v "$PWD":/app -w /app php:8.4-cli php artisan key:generate --show
  ```
  Paste the output (including `base64:` prefix) as `APP_KEY=...`.
- `DB_PASSWORD` — copy the **exact same value** already in
  `/volume1/docker/customerportal/.env` (`MYSQL_PASSWORD` there). Do
  not invent a new password — this must match the existing database
  user.
- `FLASK_NETWORK_NAME` — only if step 1 found a different name.

## 3. Build and start

```bash
docker compose up -d --build
```

This builds both containers (`app` = PHP-FPM, `proxy` = nginx) and
starts them. `db` is **not** part of this compose file — it joins the
Flask stack's existing `db` container over the shared network from
step 1.

## 4. Verify

```bash
docker compose ps
docker compose logs -f app
```

Then from a browser on the same network:

```
http://<nas-ip>:8090/login
```

You should see the login page. Log in with an existing account (same
credentials as the Flask app — same `users` table).

## Verify security headers

Production starts with `CSP_REPORT_ONLY=true`. This reports Content Security
Policy violations in the browser console without blocking legitimate portal
behavior. After checking login, order creation, attachments, reports, messages,
and live notifications, set `CSP_REPORT_ONLY=false` and rebuild the app to
enforce the policy.

Keep `TRUSTED_HOSTS` limited to the exact public hostname. `TRUSTED_PROXIES=*`
is valid only while PHP-FPM remains private behind the Compose nginx service.
If port 9000 is ever published or another proxy is introduced, replace the
wildcard with the exact proxy IP or CIDR before deployment.

## Apply reviewed schema changes

Do not run an unscoped `php artisan migrate` against the shared Flask database. Its original business tables are owned by the Flask migration history. Before deploying this release, back up the database and run only the reviewed compatibility migrations introduced for these features:

```bash
docker compose exec app php artisan migrate --force --path=database/migrations/2026_09_02_010000_add_assigned_employee_to_customers_table.php
docker compose exec app php artisan migrate --force --path=database/migrations/2026_09_02_020000_create_teams_tables.php
docker compose exec app php artisan migrate --force --path=database/migrations/2026_09_03_000000_create_product_returns_tables.php
docker compose exec app php artisan migrate --force --path=database/migrations/2026_09_07_000000_make_team_members_user_unique.php
docker compose exec app php artisan migrate --force --path=database/migrations/2026_09_08_000000_replace_employee_role_with_office_and_agent.php
docker compose exec app php artisan migrate --force --path=database/migrations/2026_09_08_010000_add_soft_deletes_to_purchase_orders.php
docker compose exec app php artisan migrate --force --path=database/migrations/2026_09_08_020000_create_failed_jobs_table.php
docker compose exec app php artisan migrate --force --path=database/migrations/2026_09_08_030000_remove_reviewing_order_status.php
docker compose exec app php artisan migrate --force --path=database/migrations/2026_09_09_000000_add_attachment_to_product_returns_table.php
docker compose exec app php artisan migrate --force --path=database/migrations/2026_09_10_000000_add_two_factor_authentication_to_users_table.php
docker compose exec app php artisan migrate --force --path=database/migrations/2026_09_10_030000_add_public_ids_to_route_resources.php
docker compose exec app php artisan migrate --force --path=database/migrations/2026_09_10_040000_add_status_submitted_at_index_to_purchase_orders.php
docker compose exec app php artisan migrate --force --path=database/migrations/2026_09_11_000000_rename_submitted_status_to_pending.php
```

Unlike the others above, `2026_09_11_000000_rename_submitted_status_to_pending` is not purely
additive -- it rewrites the live `status` column value `'submitted'` to `'pending'` on every
existing row and swaps the CHECK constraint accordingly. `app/Models/PurchaseOrder.php`'s
`STATUS_SUBMITTED` constant previously mirrored the Flask app's `app/models.py` as the shared
source of truth for this value; that mirroring is intentionally broken by this migration. If the
Flask app still reads or writes `purchase_orders.status` anywhere, it will start writing/comparing
against `'submitted'` while this app expects `'pending'` -- back up the database before running
this one, and confirm Flask no longer touches this table (or has been updated to match) first.

Migrations already applied in a prior deploy are skipped automatically, so it's
safe to re-run the whole list above rather than track which ones are new.

Laravel records which targeted changes have already run. The role migration converts every legacy `employee` account to `agent`; assign company-wide operational users to `office` after deployment. The order archival migration adds `deleted_at`, which is required before the updated order model can serve requests. The two-factor migration adds nullable encrypted-authentication fields and does not change existing sign-ins until an account completes enrollment from Settings > Security.

Review failed asynchronous deliveries with `docker compose exec app php artisan queue:failed`. Individual channel outcomes also remain visible in each order's message log.

## Realtime services

`docker compose up -d --build` also starts three private services: `reverb`
holds WebSocket connections, `broadcast-worker` consumes the dedicated
`broadcasts` and `notifications` queues, and `redis` stores those queues. Nginx proxies `/app` and
`/apps` to Reverb, so the browser uses the same public origin as the portal.

Before a production build, set unique `REVERB_APP_ID`, `REVERB_APP_KEY`, and
`REVERB_APP_SECRET` values and copy the public key to
`VITE_REVERB_APP_KEY`. Keep `REVERB_ALLOWED_ORIGINS` restricted to the exact
public browser origins. The `VITE_REVERB_*` values are compiled into the JS
bundle, so changing them requires another image build.

For a graceful Reverb-only restart after a deployment or configuration
change, run:

```
docker compose exec app php artisan reverb:restart
```

Then confirm all persistent processes are running:

```
docker compose ps app reverb broadcast-worker scheduler redis proxy
```

After the scheduler has been running for up to two minutes, verify the deeper
readiness probe. It checks MySQL, Redis, the scheduler heartbeat, and a heartbeat
that has passed through the real queue worker:

```bash
curl --fail --silent --show-error https://customerportal.theomeds.com/health/ready
```

An HTTP 503 response names the unavailable check without exposing credentials.
Use the returned `request_id` to correlate the request with `docker compose logs`.

## Backups and restore drills

The customer portal shares MySQL with the Flask application and stores private
order/return uploads in the `laravel_storage` volume. Back up both together. Find
the existing MySQL container name with `docker ps`, then run:

```bash
chmod +x scripts/backup-production.sh
DB_CONTAINER=customerportal-db-1 ./scripts/backup-production.sh
```

Override `BACKUP_ROOT` if the NAS backup destination differs from
`/volume1/docker/backups/customerportal-laravel`. The script writes UTC-dated,
owner-only archives, validates gzip/tar integrity before accepting them, and
writes a SHA-256 manifest. It deliberately excludes `.env` and other secrets.
Copy the completed archives to a second device or protected offsite target; a
backup stored only on the same NAS does not protect against NAS loss.

Verify an archive set before a restore drill:

```bash
cd /volume1/docker/backups/customerportal-laravel
sha256sum --check 20260910T000000Z-SHA256SUMS
gzip -t 20260910T000000Z-database.sql.gz
tar -tzf 20260910T000000Z-private-uploads.tar.gz >/dev/null
```

Perform database restore drills into a disposable MySQL database, never directly
over the shared production database. Import the decompressed SQL, start an
isolated app against that database, restore the private upload archive into an
empty volume, and confirm login, order history, and a private attachment. Record
the date and duration of each drill. Run the backup daily and a restore drill at
least quarterly.

## Automatic reminders and escalation

Reminder delivery is disabled by default. Apply only the two reviewed additive
migrations below; do not run an unrestricted `php artisan migrate` against the
shared Flask database.

```bash
sudo docker compose exec app php artisan migrate --path=database/migrations/2026_09_10_010000_create_order_follow_ups_table.php --force
sudo docker compose exec app php artisan migrate --path=database/migrations/2026_09_10_020000_add_follow_up_fields_to_purchase_order_notifications.php --force
```

Inspect existing open work without writing reminder state:

```bash
sudo docker compose exec app php artisan orders:reconcile-follow-ups --dry-run
```

After reviewing the counts, create the state with a 24-hour rollout grace
period. This prevents a deployment from immediately notifying every old order.

```bash
sudo docker compose exec app php artisan orders:reconcile-follow-ups --grace-hours=24
sudo docker compose exec app php artisan schedule:list
```

In **Settings -> Notifications -> Reminders and escalation**, first
enable portal reminders while customer reminder texts remain paused. Verify one
test order for each applicable workflow. Enable customer reminder texts only
after confirming the Semaphore sender name and credit balance.

The production worker must consume the `notifications` queue and receive the
Semaphore variables. Recreate `app`, `scheduler`, and `broadcast-worker` after
changing these values. To stop sending immediately, pause automatic reminders
in Settings; queued jobs check the switch again when they execute.

## Server-side sessions

Production sessions are stored in Redis database 2 so an ordinary logout
revokes the current session on the server. Queue data uses Redis database 0 and
cache data uses database 1. Set these values in production `.env`:

```dotenv
SESSION_DRIVER=redis
SESSION_CONNECTION=sessions
REDIS_SESSION_DB=2
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_PARTITIONED_COOKIE=false
```

Switching from cookie sessions signs out every user once. Validate the Compose
configuration, recreate every PHP service, clear cached configuration, and
confirm the resolved session settings:

```bash
sudo docker compose config --quiet
sudo docker compose up -d --force-recreate app scheduler broadcast-worker reverb
sudo docker compose exec app php artisan optimize:clear
sudo docker compose exec app php artisan config:show session
sudo docker compose exec app php artisan config:show database.redis.sessions
sudo docker compose ps app scheduler broadcast-worker reverb redis proxy
```

The application intentionally fails authentication closed if Redis is
unavailable; do not fall back to client-stored cookie sessions.

## If something's wrong

- **`app` container unhealthy / can't reach `db`**: almost always the
  network name from step 1 — double check `FLASK_NETWORK_NAME` in
  `.env` matches `docker network ls`'s actual output, then
  `docker compose up -d --build` again.
- **500 error, blank page**: `docker compose logs app` — logs go to
  stderr, so they'll show there directly (see `LOG_CHANNEL=stderr` in
  `.env`).
- **File uploads (PO attachments) disappear after a rebuild**: check
  the `laravel_storage` named volume exists (`docker volume ls`) — it
  should persist across `docker compose up -d --build` runs; it's only
  lost if someone runs `docker compose down -v`.

## Scope of this deployment

- Private only — reachable on the NAS's local network, no public
  domain or Cloudflare tunnel. That's a deliberate, separate decision
  to make later if this is confirmed working and wanted publicly.
- Email and Facebook Messenger notifications stay disabled (no live
  SMTP/Meta credentials configured) — see
  `app/Support/OrderNotifications.php` and
  `app/Support/FacebookMessenger.php`.
- The live Flask app and its own `docker-compose.yml` are completely
  untouched by any of this.
