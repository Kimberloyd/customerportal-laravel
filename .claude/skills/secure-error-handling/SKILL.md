---
name: secure-error-handling
description: "Implements and audits environment-aware error handling using a 3-part pattern: (1) detailed error info (stack traces, DB queries, library/framework versions) is only ever returned to the client in development, never production; (2) every error is routed to server-side logging with full detail (stack trace + user/session context) instead of being exposed in the response; (3) generic, user-friendly error pages/responses are used for every error case, revealing nothing about internals. Use when building or reviewing error handling, exception middleware, or 500/error pages -- especially AI-generated error handlers, which commonly return raw stack traces and internal details straight to the client by default. Trigger when adding try/catch or error-handling middleware, reviewing error responses for security issues, or asked about stack traces leaking to users, verbose error pages, information disclosure via errors, or debugging info showing up in production."
---

# Secure, Environment-Aware Error Handling

## The problem this fixes

AI-generated error handling commonly does the developer-friendly thing by default: catch the error, and return it -- stack trace, database query string, file paths, library and framework versions, sometimes even environment variables baked into an error message -- straight into the HTTP response. That's genuinely useful while you're developing locally. It's also a gift to an attacker in production: a stack trace tells them your framework version (so they can look up known CVEs), your file structure, your ORM and its exact query, and sometimes even fragments of your source code, all for free, just by triggering an error.

The fix isn't "never show errors" -- it's making error handling **environment-aware**, and making sure detail never disappears, it just moves from the response to a place only you can see it.

## Step 1: Return detailed errors only in development, generic messages in production

Every error handler needs to branch on environment, not return the same payload everywhere:

- In development (`NODE_ENV=development` or equivalent), it's fine -- useful, even -- to return the full error: message, stack trace, relevant context. That's what makes local debugging fast.
- In production, the client gets a generic, user-friendly message ("Something went wrong. Please try again.") and, where useful, a correlation/request ID the user can quote to support -- never the stack trace, the raw exception message, the query that failed, or any internal identifier that isn't meant to be public.
- This check needs to be centralized in one error-handling middleware/handler, not decided ad hoc per route -- otherwise it's easy for a new route to accidentally leak detail because its author didn't think about environment at all.
- Don't rely on `NODE_ENV` (or equivalent) being set correctly as the only safeguard for something this sensitive -- if your deployment pipeline can plausibly forget to set it, default to the safe (generic) behavior when the environment variable is missing or unrecognized, not the verbose one.

See `references/environment-aware-responses.md` for concrete before/after examples in Node/Express, Django, and Laravel.

## Step 2: Route every error to server-side logging with full detail

Hiding detail from the client doesn't mean losing it -- it means the detail goes somewhere only you can see, not into the response body:

- Every error your app throws should be logged server-side with everything useful for debugging: the full stack trace, the request path/method, relevant user/session context (user ID, not passwords/tokens), and a timestamp -- ideally with a correlation/request ID that ties it back to whatever generic message or ID the client received.
- This should happen in the same centralized error handler as Step 1, not scattered `console.log`s in individual routes -- one place that both decides what the client sees and unconditionally logs the full detail, so nothing is ever silently dropped.
- Use a real logging setup (a logging library/service -- e.g. Winston/Pino, Sentry, CloudWatch, or your stack's equivalent) rather than only `console.error`, so errors are searchable and retained rather than scrolling off a terminal.
- Never log secrets themselves (passwords, full tokens, API keys) even server-side -- log that authentication failed and why, not the credential that was submitted.

See `references/server-side-logging.md` for concrete logging setups and what to capture per error.

## Step 3: Use generic error pages/responses for every error case

The client-facing side of this should be uniform and reveal nothing, across every kind of failure -- not just the ones that happen to be top of mind:

- 404s, 500s, validation errors, auth failures, rate-limit responses, and any other error case should all return a consistent, generic, user-friendly shape -- no default framework error pages (which often include version banners or stack traces), no endpoint-specific one-off messages that happen to leak an internal name or table name.
- Cover every error path, not just the obvious `try/catch` blocks: unhandled promise rejections, uncaught exceptions, and framework-level default error pages all need to be routed through the same generic-response behavior -- an unhandled case is exactly the kind of path most likely to fall back to a framework's verbose default.
- "User-friendly" doesn't mean "unhelpful" -- pair the generic message with a correlation/request ID (from Step 2) so a real user has something concrete to report, without the message itself disclosing anything about your internals.

See `references/generic-error-responses.md` for a checklist of error paths to cover and example generic response shapes.

## The core principle

Detail doesn't disappear when you make error handling secure -- it moves. The client always gets the same generic, friendly response regardless of what actually broke; you always get the full detail, but only in your logs, never in the response body, and only ever in the client-facing response when you're in development.
