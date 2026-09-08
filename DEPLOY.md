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
```

Each migration guards pre-existing tables or columns and Laravel records which targeted changes have already run. The role migration converts every legacy `employee` account to `agent`; assign company-wide operational users to `office` after deployment. The order archival migration adds `deleted_at`, which is required before the updated order model can serve requests.

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

Then confirm all persistent processes are healthy:

```
docker compose ps app reverb broadcast-worker redis proxy
```

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
