---
name: laravel-reverb-implementation
description: "Sets up and hardens Laravel Reverb (Laravel's first-party WebSocket/broadcasting server) end to end -- local development configuration, frontend Echo wiring, private/presence channel authorization, and production deployment (process supervision, reverse proxy, TLS, horizontal scaling, monitoring). Use whenever the user wants to add real-time features (live notifications, chat, presence/typing indicators, live dashboards) to a Laravel app, mentions Reverb, WebSockets, Laravel Echo, Pusher-compatible broadcasting, or broadcasting events in a Laravel context, or asks to review/deploy/scale/fix an existing Reverb setup. Also trigger if the user reports broadcasts silently not arriving, WebSocket connections dropping, or asks how to deploy Reverb to a VPS/production server."
---

# Laravel Reverb: Local & Production Implementation

## Why this exists

Reverb is a real WebSocket server, not a stateless HTTP endpoint -- that difference is where almost every mistake in a Reverb setup comes from. It has to be *running continuously* (a queue worker won't save you if the server itself isn't up), it holds open connections that a normal deploy will drop, it needs a queue worker of its own to actually flush broadcasts, and its reverse-proxy config has to explicitly ask for a protocol upgrade that a default Nginx server block doesn't grant. None of these are exotic — they're the standard shape of running a persistent connection server behind infrastructure built for request/response — but they're each an easy thing to skip if you're used to shipping ordinary Laravel routes.

Work through this in order: local setup and the frontend client first (so you have something that actually connects before worrying about scale), then channel authorization and the queue (so broadcasts are both secure and actually delivered), then production deployment. If the user only wants one piece — just local dev, or hardening an existing production deploy — do that piece, but still audit for the adjacent issues listed below; a "just fix the Nginx config" request is a bad time to notice `allowed_origins` is wildcarded and say nothing.

## Before starting

Confirm this is actually a Laravel app (`composer.json` requiring `laravel/framework`) and check whether Reverb is already partially set up: `composer.json` for `laravel/reverb`, `config/reverb.php`, and `BROADCAST_CONNECTION` in `.env`. A partial setup (package installed but no channels defined, or configured locally but never hardened for production) is the common case — audit what's there before assuming you're starting from zero.

Figure out which of the three areas below are actually in scope for this request: local development, channel authorization/broadcasting correctness, and production deployment. If the user says "set up Reverb" with no further detail, treat that as all three — a real-time feature that only works in local dev isn't done. If they've scoped it narrowly ("just help me deploy this to my VPS"), focus there but still flag anything broken in the other areas rather than silently ignoring it.

As with any audit-and-fix task: report what you found before or alongside changing it. A findings-and-fixes summary at the end (what areas were touched, what's now different, what's left as a follow-up) is what makes the diff trustworthy.

## Step 1: Local development setup

Read `references/local-setup.md` before implementing. Covers: installing Reverb (`php artisan install:broadcasting`, or the manual `composer require` + `reverb:install` path), the `.env` variables and what each actually controls (the bind-address pair vs. the public-hostname pair are easy to confuse and easy to find already confused), `config/reverb.php`, running the server, and wiring up the Laravel Echo frontend client so the browser can actually connect.

**Audit signal:** if `config/reverb.php` exists but `resources/js` has no Echo bootstrap (or it's importing `pusher-js` while pointed at a Pusher-shaped config instead of Reverb's), the backend is set up but nothing in the browser can talk to it yet.

## Step 2: Channel authorization and broadcast delivery

Read `references/channel-authorization-and-broadcasting.md` before implementing. Two separate failure modes live here, and both are common enough to check even if the user only asked about one: broadcasts that are insecure (a channel authorization callback that's too permissive, or a presence channel leaking data it shouldn't), and broadcasts that are silently never delivered (because nothing is running `queue:work` — broadcasting is queued by default, and a Reverb server with zero consumers of its own queue will just accumulate undelivered jobs while everything looks fine in the code).

**Audit signal:** any `Broadcast::channel()` closure that returns `true` unconditionally, or a private/presence channel name that doesn't embed the resource's owning ID (e.g. `orders.{orderId}` with no tenant/user check) is worth flagging even if it "works" in the demo — it's an authorization bypass waiting for someone to guess an ID.

## Step 3: Production deployment

Read `references/production-deployment.md` before implementing. This is the largest of the three areas because production is where a WebSocket server's actual differences from a normal Laravel app show up: it needs a process supervisor (Reverb dying at 3am with nothing restarting it is a silent, total outage of every real-time feature), a reverse proxy configured to allow the protocol upgrade, OS-level file descriptor limits raised (every open connection is a file descriptor), and — past a fairly low connection count — either an event-loop upgrade or horizontal scaling with Redis.

Default assumption for this skill is a self-managed Linux VPS (Nginx or equivalent + Supervisor or systemd) since that's the most common and most portable setup — if the user is on Laravel Forge or Laravel Cloud, most of this is handled by the platform already, so check what the platform provides before manually configuring something it already does for you, and say so rather than duplicating it.

**Audit signal:** `config('reverb.apps.0.allowed_origins')` still wildcarded or unset, a deploy script that kills and restarts Reverb with a hard `kill`/`systemctl restart` rather than `php artisan reverb:restart`, or a Reverb process with no supervisor entry at all (check `ps aux` / the process manager configs, not just whether it happens to be running right now) are all worth surfacing even if nobody asked specifically.

## Wrapping up

Before calling it done: confirm a queue worker is actually running (or explicitly deferred to the user's job scheduler/process manager) — a Reverb setup with no queue consumer looks complete and broadcasts nothing. Confirm `allowed_origins` in `config/reverb.php` matches real production domains, not a wildcard or the local dev value. If you touched the frontend `VITE_REVERB_*` variables, remind the user that those bake into the JS bundle at build time and need a rebuild, not just a config cache clear, to take effect. Then summarize findings and fixes across whichever of the three areas were in scope, one line each.
