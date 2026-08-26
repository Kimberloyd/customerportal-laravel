# Production deployment

This assumes a self-managed Linux VPS (Nginx + Supervisor or systemd), the most common and most portable setup. If the app deploys through Laravel Forge or Laravel Cloud, check what the platform already provides before manually configuring something it handles for you — both have built-in Reverb support that covers most of the process-management and proxy pieces below.

## Process supervision

Reverb has to run continuously, and — unlike a request-driven part of the app — nothing restarts it automatically if it dies unless something is watching it. A Reverb crash with no supervisor is a silent, total outage of every real-time feature in the app, discovered only when a user notices nothing updates anymore.

Supervisor config (`/etc/supervisor/conf.d/reverb.conf`):

```ini
[program:reverb]
process_name=%(program_name)s
command=php /home/forge/example.com/artisan reverb:start
autostart=true
autorestart=true
user=forge
redirect_stderr=true
stdout_logfile=/home/forge/example.com/storage/logs/reverb.log
stopwaitsecs=10
```

`stopwaitsecs=10` matters here specifically: Reverb needs a moment to close connections gracefully rather than being killed mid-handshake.

The queue worker(s) consuming broadcast jobs need their own supervisor program, separate from Reverb itself — a common gap is supervising Reverb and forgetting the queue worker also needs one, which reintroduces the "broadcasts silently never deliver" failure mode from `references/channel-authorization-and-broadcasting.md` the moment the ad-hoc `queue:work` process from initial setup gets killed by a reboot or deploy.

On deploy, restart Reverb gracefully rather than killing the process:

```bash
php artisan reverb:restart
```

This signals Reverb to finish up and exit cleanly (Supervisor's `autorestart` then brings it back up), rather than a hard `kill`/`systemctl restart` that drops connections mid-message. Either way, **every deploy that restarts Reverb drops every open connection** — there's no message replay in WebSockets, so anything broadcast during the gap between disconnect and reconnect is simply lost to that client. This is expected, not a bug, but the frontend needs to handle it: re-fetch current state on reconnect rather than assuming the last known state is still accurate.

```javascript
Echo.connector.pusher.connection.bind('connected', () => {
    store.refreshSince(store.lastSeenAt);
});
```

## Reverse proxy (Nginx)

The critical piece is the WebSocket protocol upgrade — a default proxy block doesn't grant this, and the failure mode (connections fail or silently fall back) doesn't always point clearly at "missing upgrade headers":

```nginx
location / {
    proxy_http_version 1.1;
    proxy_set_header Host $http_host;
    proxy_set_header Scheme $scheme;
    proxy_set_header SERVER_PORT $server_port;
    proxy_set_header REMOTE_ADDR $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";

    proxy_pass http://0.0.0.0:8080;
}
```

Reverb listens on `/app` for the WebSocket connection itself and `/apps` for its HTTP API (used for things like the presence-channel member list and triggering broadcasts) — make sure both are actually reachable through the proxy, not just the path the initial handshake happens to use in testing.

TLS terminates at Nginx in this setup; Reverb itself runs plain HTTP on its private port and never needs to know about the certificate.

## OS-level connection limits

Every open WebSocket connection holds a file descriptor open for as long as it's connected — default OS limits are tuned for short-lived HTTP connections, not thousands of persistent ones, and will silently cap how many concurrent users the server can actually hold once traffic grows.

```bash
ulimit -n   # check the current limit first
```

Raise it in `/etc/security/limits.conf`:

```
forge        soft  nofile  10000
forge        hard  nofile  10000
```

And in Nginx (`nginx.conf`):

```nginx
worker_rlimit_nofile 10000;

events {
    worker_connections 10000;
    multi_accept on;
}
```

And in Supervisor (`/etc/supervisor/supervisord.conf`):

```ini
[supervisord]
minfds=10000
```

These numbers are a reasonable production default, not a hard requirement — scale them to the connection count the app actually expects, but don't skip raising them at all and assume the OS defaults are fine for a WebSocket server.

## Event loop capacity

Reverb's default event loop (`stream_select`, from ReactPHP) is capped at roughly 1,024 concurrent connections regardless of how high the OS limits above are set — this is a PHP-level ceiling, not an OS one, and it's easy to raise every limit above and still hit a wall at ~1,024 users. For anything expected to exceed that:

```bash
pecl install uv
```

Reverb automatically switches to the `ext-uv`-backed event loop when the extension is available — no config change needed beyond installing it. Check for this specifically if the app expects meaningful concurrent WebSocket traffic; it's the kind of ceiling that doesn't show up in testing with a handful of connections and then causes a hard failure mode under real load.

## Horizontal scaling

Past what a single server can hold (whether that's the ~1,024 stream_select ceiling or the box's own resource limits), scale out rather than trying to tune one server further:

```env
REVERB_SCALING_ENABLED=true
```

This requires a dedicated Redis instance — Reverb uses the application's default Redis connection for pub/sub between instances, so **don't point this at a Redis database shared with cache or queue data that might get flushed**; a `cache:clear` or queue-related flush on a shared database would disrupt the scaling pub/sub channel. Run `reverb:start` on each server behind a load balancer; sticky sessions aren't needed since a connection stays on whichever server accepted its original handshake for the connection's whole lifetime — the load balancer only needs to distribute *new* connections, not maintain affinity for existing ones.

If using Laravel Pulse for monitoring (see below) in a horizontally-scaled setup, run the `pulse:check` daemon on exactly one server, not all of them — running it on every instance double- (or N-times-) counts everything it records.

**Load balancer idle timeout** is a common silent connection-killer in this setup: AWS ALB defaults to a 60-second idle timeout and will close a WebSocket connection that goes quiet for that long, regardless of whether Reverb itself thinks the connection is fine. Check the load balancer's idle timeout against Reverb's own ping/heartbeat interval and make sure the LB timeout is comfortably longer, not shorter.

## Security checklist before calling it production-ready

- `config('reverb.apps.0.allowed_origins')` — must list actual production domains, not the local-dev wildcard `['*']` or `localhost` left over from setup.
- `REVERB_APP_SECRET` isn't referenced by any `VITE_` variable or exposed through a debug/config-dump route — it's what signs channel authorization and should never reach the browser.
- Channel authorization callbacks (see `references/channel-authorization-and-broadcasting.md`) — check these regardless of how the deployment question was scoped, since a channel that "works" with an overly permissive callback won't look broken in a deploy checklist.

## Monitoring

Laravel Pulse can track Reverb-specific metrics (connection counts, message throughput) if the app already uses Pulse:

```php
// config/pulse.php
use Laravel\Reverb\Pulse\Recorders\ReverbConnections;
use Laravel\Reverb\Pulse\Recorders\ReverbMessages;

'recorders' => [
    ReverbConnections::class => ['sample_rate' => 1],
    ReverbMessages::class => ['sample_rate' => 1],
],
```

```blade
<x-pulse>
    <livewire:reverb.connections cols="full" />
    <livewire:reverb.messages cols="full" />
</x-pulse>
```

This is optional (skip it if the app doesn't already run Pulse — it's not worth introducing solely for this), but worth mentioning if the user is asking about production readiness and has no visibility into connection counts or message volume today.
