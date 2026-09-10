# Environment-aware error responses

The core idea: one centralized error handler decides what the client sees, and it branches on environment -- never the same payload in dev and prod.

## Node / Express

**Before (leaks everything, in every environment):**

```js
app.use((err, req, res, next) => {
  res.status(500).json({
    error: err.message,
    stack: err.stack,
    query: err.sql, // e.g. from a DB driver error
  });
});
```

**After:**

```js
const crypto = require('crypto');
const logger = require('./logger'); // see references/server-side-logging.md

app.use((err, req, res, next) => {
  const requestId = crypto.randomUUID();

  // Always log full detail server-side, regardless of environment.
  logger.error('Unhandled request error', {
    requestId,
    message: err.message,
    stack: err.stack,
    path: req.path,
    method: req.method,
    userId: req.user?.id,
  });

  const isDev = process.env.NODE_ENV === 'development';

  if (isDev) {
    return res.status(err.status || 500).json({
      error: err.message,
      stack: err.stack,
      requestId,
    });
  }

  // Default to the safe response for production AND for any unset/unrecognized
  // NODE_ENV value -- never let a missing env var fall back to verbose output.
  res.status(err.status || 500).json({
    error: 'Something went wrong. Please try again.',
    requestId,
  });
});
```

Note the `isDev` check is the only branch -- everything else (status code, logging) is identical, so there's no way for a route to opt out of having its errors routed through this.

## Django

**Before:** relying solely on `DEBUG = True/False` with no fallback thought given to what happens if it's misconfigured, and custom exception handlers that re-serialize the raw exception.

**After** (Django's `DEBUG` setting already does most of this correctly by default -- the risk is usually a custom handler re-adding the leak):

```python
# settings.py
DEBUG = env.bool("DJANGO_DEBUG", default=False)  # default to the SAFE value if unset

# views.py / a DRF exception handler
import logging
import uuid

logger = logging.getLogger("myapp.errors")

def custom_exception_handler(exc, context):
    request_id = str(uuid.uuid4())
    logger.error(
        "Unhandled request error",
        exc_info=exc,
        extra={
            "request_id": request_id,
            "path": context["request"].path,
            "user_id": getattr(context["request"].user, "id", None),
        },
    )

    if settings.DEBUG:
        # Django's built-in debug page already shows full detail locally;
        # for an API, mirror that intentionally rather than leaving DRF's
        # default (which can still be generic) as the only signal.
        return Response({"error": str(exc), "request_id": request_id}, status=500)

    return Response(
        {"error": "Something went wrong. Please try again.", "request_id": request_id},
        status=500,
    )
```

The important line is `default=False` on `DEBUG` -- if the env var that controls it is ever missing, the app fails safe (generic errors) instead of failing open (verbose errors) in what might actually be production.

## Laravel

**Before:** `APP_DEBUG=true` left on in production, or a custom `render()` that always returns `$exception->getMessage()` and a trace.

**After** (`bootstrap/app.php` / `app/Exceptions/Handler.php`):

```php
public function render($request, Throwable $e)
{
    $requestId = (string) Str::uuid();

    Log::error('Unhandled request error', [
        'request_id' => $requestId,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'path' => $request->path(),
        'user_id' => optional($request->user())->id,
    ]);

    if (config('app.debug')) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTrace(),
            'request_id' => $requestId,
        ], 500);
    }

    return response()->json([
        'error' => 'Something went wrong. Please try again.',
        'request_id' => $requestId,
    ], 500);
}
```

```
# .env (production)
APP_DEBUG=false
```

As with Django, make sure `config('app.debug')` defaults to `false` if the env var is absent -- don't let a missing `.env` entry silently enable verbose errors on a fresh deploy.

## Instructing an AI coding assistant

Be explicit rather than saying "handle errors securely" -- name the exact behavior:

> "Add centralized error-handling middleware. In development, return the full error and stack trace. In production (and as the default if the environment can't be determined), return only a generic message and a request ID. Log the full error server-side in both cases."
