# Flask elimination — historical record

**Status: Flask is retired. This app owns its own database outright.**
This document used to track an active shared-MySQL coupling with a
legacy Flask app; it's kept now as the historical record of what that
coupling was and how each piece was closed out, for anyone who runs
into old references to Flask in git history, migration comments, or
elsewhere in this repo. See `DEPLOY.md`'s "Flask retirement" section
for what this means operationally.

## What the coupling used to be

Laravel and the legacy Flask app (`/volume1/docker/customerportal` on
the NAS) shared one MySQL database directly — no table prefix, no
schema split, both apps reading and writing the same tables. Verified
2026-09-12 against a local snapshot of Flask's actual source
(`app/models.py`, `app/auth/auth_routes.py`,
`app/purchase_orders/purchase_order_routes.py`, snapshot dated
2026-08-05). Two rows below were originally mislabeled from assumption
rather than confirmed against Flask's code; that pass corrected them
before the elimination decision made the distinction moot.

| Table | What was shared | Resolution |
|---|---|---|
| `users` | Signed-int IDs (ported from Flask's schema, not Laravel's default `bigint`) | Eliminated 2026-09-12 — Laravel now owns this table outright on its own database. Signed-int IDs kept as-is (see below), not because of Flask but because converting them is unrelated, invasive work with no functional benefit now. |
| `login_attempts` | Turned out never to have been actually shared — Flask's `app/auth/auth_routes.py` has no lockout logic and never referenced this table. Always Laravel-only. | N/A |
| `customers` | Three-way write overlap on `company_name`/`channel`: Flask, `customers:sync` (external inventory API), and (separately, non-overlapping fields) Laravel's own account-linking code. | Eliminated 2026-09-12 — Flask's write path is gone. `customers:sync` continues unchanged; it was never in conflict with anything except Flask. |
| `purchase_orders` | Flask's `update_order_delivery_status()` wrote the literal `'submitted'` status; a since-tightened Laravel CHECK constraint made those writes fail starting with the 2026-09-11 status-rename deploy. A same-day stopgap widened the constraint to keep Flask's writes from erroring while the real handoff was pending. | Eliminated 2026-09-12 — the stopgap (`2026_09_12_010000_allow_legacy_submitted_status_on_purchase_orders`, `orders:normalize-legacy-submitted-status`) was itself retired by `2026_09_12_020000_retire_legacy_submitted_status_stopgap`, since nothing can write `'submitted'` again. |

## Full write-path sweep (2026-09-12, historical)

Every model in Flask's `app/models.py` was checked against Laravel's
schema for CHECK-constraint or NOT-NULL mismatches before the
elimination decision — `purchase_orders` (above) was the only live
incident found:

| Flask model / table | Finding |
|---|---|
| `Product` / `products` | Already decoupled before this — `2026_08_18_000000_drop_products_table` dropped Laravel's own copy of this table and its FK entirely back when products started coming live from the inventory API instead. |
| `PurchaseOrderItem` / `purchase_order_items` | Laravel's three `CheckConstraint`s matched Flask's own exactly — never at risk. |
| `PurchaseOrderAudit` / `purchase_order_audits` | Free-text `action` column on both sides, no CHECK constraint on either — never at risk. |
| `CustomerMessage` / `customer_messages` | Laravel's `status`/`channel`/`sender_type` columns were always plain defaulted strings with no CHECK constraint — never at risk. |

## Auth cutover (closed 2026-09-12, before elimination)

- **Rehash on login**: every legacy Werkzeug-hashed account was
  upgraded to bcrypt on its next login, ahead of Flask's actual
  retirement.
- **Coverage tracking**: `php artisan security:legacy-passwords`
  confirmed 0 legacy accounts remaining in production before
  `LegacyPasswordHasher` was removed. The weekly scheduled check
  (`routes/console.php`, Mondays 03:00) stays in place as a permanent
  regression guard.
- **`session_version`**: turned out to have never been a Flask
  concern — Flask's `User` model never had this column. Always
  Laravel-only.
- **`LegacyPasswordHasher`**: removed entirely (class, unit tests, the
  `vinsaj9/scrypt` Composer dependency, and every call site) once
  coverage confirmed 100%.

## Infrastructure changes made for elimination (2026-09-12)

- **`docker-compose.yml`**: added a `db` service (MySQL, owned by this
  Compose project, its own `mysql_data` volume). Removed the external
  `flask` Docker network entirely, and removed it from `app`,
  `broadcast-worker`, and `scheduler`'s network lists — they now only
  need `db`, which is a normal `depends_on: service_healthy`
  dependency like every other service.
- **`.env.production.example`**: removed `FLASK_NETWORK_NAME`; added
  `MYSQL_ROOT_PASSWORD` for the new `db` container; `DB_PASSWORD` is
  now a value to invent, not to copy from Flask's `.env`.
- **`DEPLOY.md`**: removed the "confirm the Flask network name" setup
  step, the scoped-migration requirement (`scripts/migrate-shared-db.sh`
  was deleted — a normal `php artisan migrate --force` is safe now),
  and the shared-backup framing. Added a first-boot migrate+seed step,
  since a fresh `db` container starts with no accounts at all.
- **`database/seeders/DatabaseSeeder.php`**: fixed to actually match
  this app's real `User` schema (it was unmodified Breeze boilerplate,
  `name`/`email` fields that don't exist on this model) and now
  creates a genuine first admin account for a fresh deploy.

## Signed-int IDs — still deliberately left as-is

`users.id` and every FK that references it use a plain signed
`integer` instead of Laravel's default `bigint unsigned`, because
that's what Flask's original schema used. This was **not** converted
as part of elimination: it's a stylistic convention mismatch, not a
functional Flask dependency — MySQL doesn't care, and converting it
touches nearly every table in the schema (`users`, `customers`,
`purchase_orders`, and roughly ten more via FK) for no elimination
benefit. If it's ever worth doing, it's a standalone piece of work on
its own merits, unrelated to Flask.
