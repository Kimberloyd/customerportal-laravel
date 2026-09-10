# Generic, user-friendly error responses -- for every error path

Detail-hiding is only as strong as its weakest path. It's easy to secure the `try/catch` blocks you wrote and still leak through a path you didn't think of.

## Error paths to cover, not just the obvious ones

- **Explicit `try/catch` / caught exceptions** -- the easy case, usually already routed through your centralized handler if Step 1/2 are done.
- **Unhandled promise rejections / uncaught exceptions** (Node: `process.on('unhandledRejection', ...)`, `process.on('uncaughtException', ...)`) -- if these aren't wired to the same handler, they commonly fall through to the runtime's own default output, which can include a full stack trace on stdout/stderr or even in the response depending on your server setup.
- **Framework default error pages** -- most frameworks ship a verbose default error page for local development (Express's default HTML error handler, Django's debug page, Laravel's Whoops page, Rails' default) that is *meant* to be replaced or disabled in production. Confirm it actually is, don't assume the framework does this for you automatically in every case.
- **Validation errors** -- a 400 response for bad input is fine to be specific about *what* was wrong with the input ("email is required"), but should never echo back raw internal validation-library error objects or schema internals.
- **404s** -- a "not found" for a resource that exists but the user doesn't own should look identical to a "not found" for a resource that genuinely doesn't exist (see the IDOR/BOLA skill for why this matters) -- don't let a 404 handler accidentally reveal which case it is.
- **Rate-limit / 429 responses** -- fine to tell the user they're rate-limited, not fine to reveal your exact limits/algorithm/internal bucket keys.
- **Auth failures (401/403)** -- "invalid credentials" is fine; don't reveal whether it was the username or the password that was wrong (that turns a login form into a username-enumeration oracle), and don't leak token-validation internals (e.g. "JWT signature verification failed with key id xyz").
- **Reverse proxy / load balancer error pages** (502/503/504) -- often outside your app code entirely; worth checking your proxy/CDN config (nginx, Cloudflare, etc.) doesn't have its own verbose default enabled.

## Example generic response shapes

```json
// Node/Express, JSON API
{ "error": "Something went wrong. Please try again.", "requestId": "a1b2c3d4" }
```

```json
// Validation error -- specific about the input, not internals
{ "error": "Email is required.", "field": "email" }
```

```json
// Auth failure -- deliberately vague about which credential was wrong
{ "error": "Invalid email or password." }
```

For a server-rendered app, the equivalent is a generic branded error page (not the framework's default) for 404/500, with no stack trace and no framework/version banner in the page footer or headers (also check the `X-Powered-By` header and similar -- strip these in production).

## Checklist

1. Trigger a genuinely unhandled error (not one you wrote a `catch` for) in a non-development environment and confirm the response is generic, not a framework default.
2. Check 404, 401, 403, 429, and 500 all return the same generic *shape* (even though the message text differs appropriately).
3. Check response headers for anything revealing (`X-Powered-By`, detailed `Server` headers, stack-trace-bearing custom headers).
4. Confirm the reverse proxy/CDN in front of the app doesn't have its own verbose error page enabled for upstream failures.
5. Confirm every one of the above still produces a full, detailed entry in server-side logs (per `server-side-logging.md`) -- generic to the client should never mean silent to you.
