# Local development setup

## Installing

The current standard path is the broadcasting installer, which also scaffolds Reverb's config:

```bash
php artisan install:broadcasting --reverb
```

(Running `php artisan install:broadcasting` alone will prompt for which broadcaster to use — pass `--reverb` to skip the prompt when you already know.) This installs the `laravel/reverb` package, publishes `config/reverb.php`, adds the Reverb block to `config/broadcasting.php`, and adds the `REVERB_*` variables to `.env`.

If the project already has broadcasting configured for something else and you're adding Reverb manually instead:

```bash
composer require laravel/reverb
php artisan reverb:install
```

## Environment variables — two pairs, different jobs

This is the single most common source of confusion in a Reverb setup, including in already-deployed apps, so check it explicitly rather than assuming it's right:

```env
# What the PHP process itself binds to
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

# What browsers and other app servers actually connect to
REVERB_HOST=ws.example.com
REVERB_PORT=443
REVERB_SCHEME=https
```

`REVERB_SERVER_HOST`/`REVERB_SERVER_PORT` control where the `reverb:start` process listens — in production this is almost always a private port behind a reverse proxy, not the public-facing one. `REVERB_HOST`/`REVERB_PORT`/`REVERB_SCHEME` are what gets handed to the frontend client so it knows where to actually open the WebSocket. Setting only one pair, or setting them to the same value, is the kind of thing that works by accident in local dev (where they're often the same) and breaks the moment there's a reverse proxy in front of Reverb.

Application credentials, generated at install time:

```env
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
```

`REVERB_APP_KEY` is the only one of these three that's meant to reach the browser (via `VITE_REVERB_APP_KEY`, see below). `REVERB_APP_SECRET` signs the private/presence channel authorization — treat it like any other server-side secret and make sure it isn't accidentally exposed through a `VITE_` variable or a debug endpoint.

Tell Laravel to actually use Reverb as the broadcaster:

```env
BROADCAST_CONNECTION=reverb
```

## config/reverb.php

```php
'apps' => [
    [
        'app_id' => env('REVERB_APP_ID'),
        'app_key' => env('REVERB_APP_KEY'),
        'app_secret' => env('REVERB_APP_SECRET'),
        'allowed_origins' => ['*'], // fine for local dev; lock this down before production — see production-deployment.md
        // ...
    ],
],
```

Local HTTPS (e.g. via Herd or Valet, when a frontend feature needs a secure context to test): Reverb can terminate TLS itself using a local certificate —

```php
'options' => [
    'tls' => [
        'local_cert' => '/path/to/cert.pem',
    ],
],
```

— though in most local setups it's simpler to run Reverb on plain HTTP and let the browser talk to it directly on `localhost`.

## Running the server

```bash
php artisan reverb:start                              # binds 0.0.0.0:8080 by default
php artisan reverb:start --host=127.0.0.1 --port=9000  # explicit bind
php artisan reverb:start --debug                       # verbose connection/message logging — useful when nothing seems to arrive
```

Reverb has to be running continuously for anything real-time to work at all — unlike a queue worker, there's no "it'll catch up next request." In local dev that usually means a dedicated terminal tab or a `Procfile`/`composer run dev` entry alongside `queue:listen` and the Vite dev server; treat "is Reverb actually running" as the first thing to check when a real-time feature "isn't working."

## Frontend: Laravel Echo

```bash
npm install --save-dev laravel-echo pusher-js
```

Reverb speaks a Pusher-compatible protocol, which is why `pusher-js` is a dependency even though nothing here talks to actual Pusher infrastructure. Bootstrap Echo (typically in `resources/js/echo.js`, imported from `app.js`):

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

The `VITE_REVERB_*` variables mirror the server-facing `REVERB_HOST`/`REVERB_PORT`/`REVERB_SCHEME`/`REVERB_APP_KEY` values so Vite can inline them into the built JS bundle:

```env
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

These are baked in at build time, not read at runtime — `npm run build` (or `npm run dev` for local) has to actually re-run after any of them change. A production `.env` edit followed by `php artisan config:cache` and nothing else will not update these; this is a common "I changed the env var but nothing changed" trap, worth checking for explicitly if the user reports exactly that symptom.

## Verifying it actually works

A quick end-to-end check before moving on: fire a test broadcast (`php artisan tinker` and dispatch an event that implements `ShouldBroadcast`, or a dedicated test route) with `reverb:start --debug` running in one terminal and the frontend open in a browser with dev tools' Network/WS tab open. You should see the connection handshake, the subscription message, and the broadcast event arrive, in that order. If the handshake succeeds but nothing after that arrives, the next place to look is the queue (see `references/channel-authorization-and-broadcasting.md`) — Reverb only ever sees a broadcast after a queue worker has processed the job.
