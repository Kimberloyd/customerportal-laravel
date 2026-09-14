# Deploying to the NAS

This app is fully self-contained — its own MySQL (`db`), its own Redis,
its own everything. It used to share a database with a legacy Flask
app on the same NAS; that arrangement ended and Flask is retired (see
`docs/flask-coupling.md` for the full history of what that coupling
was and how it was closed out).

These commands are meant to be run **on the NAS terminal**, in
`/volume1/docker/customerportal-laravel` (this directory, once the
latest commit has synced there).

## 1. Create `.env`

```bash
cp .env.production.example .env
```

Then edit `.env` and fill in:

- `APP_KEY` — generate one:
  ```bash
  docker run --rm -v "$PWD":/app -w /app php:8.4-cli php artisan key:generate --show
  ```
  Paste the output (including `base64:` prefix) as `APP_KEY=...`.
- `DB_PASSWORD` — invent a real password for this app's own database
  user (this is a fresh database now, not a value to copy from
  anywhere).
- `MYSQL_ROOT_PASSWORD` — invent a separate real password. Only the
  `db` container itself uses this (to bootstrap the database/user
  above); Laravel never touches it.

## 2. Build and start

```bash
docker compose up -d --build
```

This builds `app` (PHP-FPM) and `proxy` (nginx), and brings up every
other service in `docker-compose.yml` — including `db`, which is now
part of this compose project and owned entirely by it.

## 3. Migrate and seed

First boot only — a fresh `db` container has no schema and no accounts
yet:

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
```

This creates one admin account: `admin@theomeds.com` / `password`.
**Log in and change that password immediately** — it's a well-known
default, not a real secret, and this instance is reachable at a real
public hostname (see the Cloudflare Tunnel setup below).

## 4. Verify

```bash
docker compose ps
docker compose logs -f app
```

Then from a browser on the same network:

```
http://<nas-ip>:8090/login
```

You should see the login page. Log in with the admin account from
step 3, then create real accounts for everyone else from **Admin ->
Users** and retire the seeded admin credential once you have another
admin account to use instead.

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

## Apply schema changes on later deploys

Now that this app owns its database outright, deploying a new release
is the normal Laravel way -- no more scoped migration list, no more
backup-first ritual for routine schema work:

```bash
docker compose exec app php artisan migrate --force
```

Migrations already applied in a prior deploy are skipped automatically.
(`docs/flask-coupling.md` has the history of why this used to be far
more careful -- a shared-database arrangement with a since-retired
Flask app. `scripts/migrate-shared-db.sh` and its `I_HAVE_A_BACKUP`
gate are no longer needed and have been removed.)

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
docker compose ps app reverb broadcast-worker scheduler redis db proxy
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

This app's own `db` container and the `laravel_storage` volume (private
order/return uploads) need backing up together. Find the exact
container name with `docker compose ps db`, then run:

```bash
chmod +x scripts/backup-production.sh
DB_CONTAINER=customerportal-laravel-db-1 ./scripts/backup-production.sh
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
over the production database. Import the decompressed SQL, start an
isolated app against that database, restore the private upload archive into an
empty volume, and confirm login, order history, and a private attachment. Record
the date and duration of each drill. Run the backup daily and a restore drill at
least quarterly.

## Automatic reminders and escalation

Reminder delivery is disabled by default. The two migrations it needs
are covered by the normal `php artisan migrate --force` from "Apply
schema changes on later deploys" above -- nothing extra to run here.

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

- **`app` container unhealthy / can't reach `db`**: check `docker
  compose logs db` first -- a fresh `db` container can take a few
  healthcheck retries to finish initializing before `app` will start.
  If it's still failing after that, confirm `DB_PASSWORD` in `.env`
  matches what `db` actually bootstrapped with (only matters if `.env`
  changed after `db`'s volume was first created -- MySQL only reads
  `MYSQL_PASSWORD`/`MYSQL_DATABASE` on that container's very first
  boot).
- **500 error, blank page**: `docker compose logs app` — logs go to
  stderr, so they'll show there directly (see `LOG_CHANNEL=stderr` in
  `.env`).
- **File uploads (PO attachments) disappear after a rebuild**: check
  the `laravel_storage` named volume exists (`docker volume ls`) — it
  should persist across `docker compose up -d --build` runs; it's only
  lost if someone runs `docker compose down -v`.

## Flask retirement

This app no longer depends on the legacy Flask app in any way -- no
shared database, no shared Docker network, no shared login system.
`docs/flask-coupling.md` has the full history of what that coupling
used to be and how each piece was closed out, for anyone who runs into
old references to it in git history, migration comments, or this file.

Once every account that needs one exists in this app (see step 3
above), the Flask app's own Compose stack on the NAS
(`/volume1/docker/customerportal`) can be stopped and removed. That's
a separate action on its own project directory --
`docker compose down` there, run from the NAS terminal -- not
something this repo's tooling reaches into.
