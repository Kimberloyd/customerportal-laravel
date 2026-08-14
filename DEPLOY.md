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

## Do NOT run `php artisan migrate`

**Never run `php artisan migrate` against this database.** The
business-domain tables (`users`, `customers`, `products`,
`purchase_orders`, etc.) already exist — created by the Flask app's
own Alembic migrations — and this Laravel app's migrations describe
that *same* schema so Eloquent can read/write it directly. Running
`migrate` would try to `CREATE TABLE users` (etc.) against a table
that's already there and fail, or worse.

This deployment is configured with `SESSION_DRIVER=file`,
`CACHE_STORE=file`, and `QUEUE_CONNECTION=sync` specifically so
Laravel never needs its own `sessions`/`cache`/`jobs` tables either —
there is genuinely no migration this deployment needs to run, ever,
against the shared database.

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
