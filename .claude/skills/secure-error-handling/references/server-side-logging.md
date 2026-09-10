# Server-side error logging

Hiding detail from the client only works if that detail still goes *somewhere* -- otherwise you've just made bugs impossible to debug. The detail goes into structured, server-side logs, captured unconditionally, regardless of environment.

## What to capture per error

- Full stack trace and error message.
- A correlation/request ID (generate one per request if you don't have one already) -- this is what ties a generic client-facing message back to the actual error in your logs, and what a user can quote to support.
- Request context: path, method, relevant route params (not secrets).
- User/session context: user ID if authenticated (not their password, token, or session secret).
- Timestamp and, if you have multiple services/instances, which one handled the request.

## What NOT to log

- Passwords, full auth tokens, API keys, credit card numbers -- even server-side. Log *that* authentication failed and why (wrong password, expired token), not the credential itself.
- Full request bodies indiscriminately if they might contain the above -- redact known-sensitive fields before logging.

## Node / Express (Winston example)

```js
// logger.js
const winston = require('winston');

const logger = winston.createLogger({
  level: 'info',
  format: winston.format.json(),
  defaultMeta: { service: 'api' },
  transports: [
    new winston.transports.Console(),
    // In production, also ship to a persistent sink:
    // new winston.transports.Http({ host: 'logs.example.com', ... }),
    // or use a service transport (Sentry, Datadog, CloudWatch, etc.)
  ],
});

module.exports = logger;
```

```js
logger.error('Unhandled request error', {
  requestId,
  message: err.message,
  stack: err.stack,
  path: req.path,
  method: req.method,
  userId: req.user?.id,
});
```

Plain `console.error` is fine for local development, but for anything deployed you want logs that are searchable, retained past a container restart, and ideally aggregated somewhere you can alert on (an error-tracking service like Sentry, or your cloud provider's log aggregation -- CloudWatch, Stackdriver, etc.).

## Django

```python
# settings.py
LOGGING = {
    "version": 1,
    "disable_existing_loggers": False,
    "formatters": {"json": {"()": "pythonjsonlogger.jsonlogger.JsonFormatter"}},
    "handlers": {
        "console": {"class": "logging.StreamHandler", "formatter": "json"},
    },
    "loggers": {
        "myapp.errors": {"handlers": ["console"], "level": "ERROR"},
    },
}
```

```python
logger.error(
    "Unhandled request error",
    exc_info=exc,  # captures the full traceback
    extra={"request_id": request_id, "path": request.path, "user_id": user_id},
)
```

## Laravel

Laravel's `Log` facade with the default `stack`/`daily` channel already captures exceptions with full trace context when you call `Log::error(...)` as shown in `environment-aware-responses.md`. For production, point the channel at a persistent sink (a daily rotating file plus a service like Sentry/Bugsnag via `config/logging.php`) rather than leaving it as `single` writing to a file nobody watches.

## Error-tracking services

For any of these stacks, consider pairing structured logs with an error-tracking service (Sentry, Bugsnag, Rollbar) that captures stack traces with source context, groups recurring errors, and can alert you -- this is usually a better long-term answer than grepping log files, especially once you have more than one server instance.

## Sanity check

If an error happens in production, you should be able to: take the request ID the user reports (or the generic error message's timestamp), find the matching log entry, and see the full stack trace, the request that triggered it, and which user it happened to -- without the user ever having seen any of that themselves.
