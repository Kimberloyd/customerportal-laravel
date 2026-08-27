# Security & Audit Checklist

Use this when reviewing/hardening an existing Semaphore integration. Go through each item against the actual code, not against what a comment or README claims.

## 1. API key handling

- [ ] Search the codebase for the literal Semaphore API key pattern and for the string `apikey=` -- confirm no request builds the key as a hardcoded literal anywhere (including test fixtures and seed scripts).
- [ ] Confirm the key is read from env/config, and that `.env`/equivalent secret files are gitignored (check `.gitignore`, and check `git log`/`git grep` history isn't the scope here, but at minimum confirm the current working tree doesn't commit it).
- [ ] Confirm the key isn't echoed into logs, error messages, or exception traces (a bare `throw new Exception($response->body())` after passing the key as a query param can leak it into logs if the URL itself is logged).
- [ ] Confirm the key isn't sent in a GET query string that could end up in server access logs for POST-capable endpoints -- Semaphore's send endpoints accept POST; make sure the code isn't using GET with the key as a query param for anything other than the documented GET endpoints (retrieval, account).

## 2. Endpoint correctness

- [ ] OTP traffic goes through `/api/v4/otp`, not `/api/v4/messages`. Grep for anywhere an OTP/verification code is concatenated into a message sent via the standard endpoint.
- [ ] Priority/urgent transactional sends (password reset alerts, fraud alerts) use `/api/v4/priority` if the product actually needs guaranteed non-queued delivery; using it for all traffic just doubles cost without benefit -- flag over-use as well as under-use.
- [ ] Bulk sends batch through the `number` parameter (comma-separated, up to 1,000) rather than looping single-recipient calls in a tight loop, which needlessly burns the 120/min limit and increases the odds of partial-batch failures being harder to reconcile.

## 3. Error / failure handling

- [ ] A non-2xx HTTP response is handled (not swallowed by an empty catch block or ignored return value).
- [ ] A 2xx response with `status: "Failed"` or `status: "Refunded"` in the body is treated as a failure, not silently treated as success just because the HTTP call succeeded.
- [ ] Rate-limit responses (429, or headers showing `X-RateLimit-Remaining: 0`) are backed off, not retried in an immediate tight loop.
- [ ] Timeouts are set on the HTTP client (no unbounded default) so a slow Semaphore response can't hang a request thread indefinitely.

## 4. OTP-specific hardening

- [ ] The OTP code is never logged (application logs, error trackers like Sentry, request/response logging middleware that might capture the full API response body).
- [ ] The OTP code is never returned in an API response to the frontend beyond what's needed (the frontend should never receive the actual code from the backend -- only a confirmation that one was sent).
- [ ] Verification has an expiry (the code should stop being valid after a reasonable window, independent of whatever Semaphore itself does).
- [ ] Verification attempts are rate-limited per number/session (protect against brute-forcing a 4-6 digit code).
- [ ] The stored code (cache/DB) is hashed or otherwise not trivially readable by anyone with read access to that store, consistent with how the codebase treats other short-lived secrets.

## 5. Sender name and message content

- [ ] `sendername` used in requests is a name actually registered on the account (`GET /account/sendernames`), not an arbitrary string that Semaphore will silently fall back away from.
- [ ] No message template accidentally begins with the literal word "TEST" (Semaphore silently ignores these) -- a common accidental bug when a debug prefix leaks into a production template.
- [ ] Long templates are checked against the 160-character auto-split boundary; if a template blows past it, flag the added cost/segmentation, don't just assume it's fine.

## 6. Capacity and monitoring

- [ ] Something in the system (a scheduled check, an alert, a dashboard) monitors `credit_balance` via `GET /account` so sends don't start silently failing once credits run out. If nothing does, that's worth flagging even if not explicitly asked.
- [ ] If the integration is high-volume, confirm there's a mechanism (queue, job retries) for re-attempting genuinely failed sends rather than a fire-and-forget call in the request/response cycle.

## 7. Consolidate, don't rewrite unnecessarily

When auditing, prefer fixing what's there in place over wholesale rewrites -- promote a working-but-flawed pattern to a fixed version, keep naming/structure consistent with the rest of the codebase, and call out clearly in your summary which specific issues were found and fixed versus which are pre-existing and out of scope.
