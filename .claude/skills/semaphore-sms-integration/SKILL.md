---
name: semaphore-sms-integration
description: "Implements and audits Semaphore (semaphore.co) SMS/OTP integration in any codebase -- standard, priority, and bulk SMS sending, OTP verification flows, message retrieval, and account/credit checks. Always reads the target codebase first (language, framework, existing HTTP client, config/env conventions, existing notification abstractions) before writing code, so the integration matches how the project already does things rather than bolting on a foreign pattern. Use whenever asked to add SMS sending, text message notifications, or OTP/2FA verification via Semaphore; when the user mentions Semaphore, semaphore.co, or its API key/endpoints; or when reviewing/hardening an existing Semaphore integration for issues like hardcoded API keys, OTP codes logged in plaintext, missing retry/error handling, or misuse of the wrong endpoint (e.g. sending OTPs through the plain messages endpoint instead of /otp)."
---

# Semaphore SMS Integration

Semaphore (semaphore.co) is an SMS API for sending text messages to Philippine mobile numbers. It exposes a small set of REST endpoints (standard messages, priority messages, OTP, message retrieval, account info) authenticated by a single API key sent as a request parameter.

This skill covers two jobs: **implementing** a new Semaphore integration, and **auditing/hardening** an existing one. Both start the same way -- by reading the codebase, not by assuming a stack.

## Step 1: Read the codebase before writing anything

Never scaffold a Semaphore client from scratch without first checking what the project already does. Look for:

- **Language/framework**: `composer.json` (PHP/Laravel), `package.json` (Node.js/Express/Next), `requirements.txt`/`pyproject.toml` (Python/Django/Flask), `*.csproj` (.NET), `Gemfile` (Ruby/Rails), `go.mod` (Go).
- **Existing HTTP client**: is there already a Guzzle client, an axios/fetch wrapper, an `httpx`/`requests` session, an `HttpClient` instance, a `Faraday` connection? Reuse it instead of introducing a second HTTP library.
- **Existing notification/messaging abstraction**: many codebases already have a `NotificationService`, a mailer-style interface, or a `Channels/` folder (Laravel notification channels, a `notifiers/` directory, etc). A new SMS capability should plug into that abstraction as a new channel/provider, not live as a disconnected one-off script.
- **Config/env conventions**: `.env` + `config/services.php` (Laravel), `.env` + `process.env` (Node), `settings.py` (Django), `appsettings.json` (.NET). Match whatever pattern already exists for other third-party API keys (Stripe, Twilio, etc.) in the project -- same file, same naming style (`SEMAPHORE_API_KEY` vs `SEMAPHORE_KEY` vs `Semaphore__ApiKey`).
- **Existing tests**: a `tests/` or `__tests__/` directory with HTTP mocking conventions (e.g. Laravel's `Http::fake`, `nock`, `responses`/`respx`, `WebMock`) -- new code should be testable the same way.

If nothing like this exists yet, build the smallest reasonable version of it (a single client/service class) rather than a large abstraction the project doesn't need yet.

See `references/codebase-detection.md` for a fuller checklist and stack-specific signals.

## Step 2: Know the actual API surface

Read `references/api-reference.md` for the full endpoint reference (exact URLs, params, responses, rate limits). The essentials:

| Endpoint | Method | Purpose | Rate limit | Cost |
|---|---|---|---|---|
| `/api/v4/messages` | POST | Standard SMS (1-1000 recipients, comma-separated) | 120/min | 1 credit / 160 chars |
| `/api/v4/messages` | GET | Retrieve sent messages (filters, pagination) | 30/min | -- |
| `/api/v4/priority` | POST | Time-sensitive SMS, bypasses the queue | unlimited | 2 credits / 160 chars |
| `/api/v4/otp` | POST | OTP codes -- dedicated route, `{otp}` placeholder or auto-appended | unlimited | 2 credits / 160 chars |
| `/api/v4/account` | GET | Account info + credit balance | 2/min | -- |
| `/api/v4/account/transactions` | GET | Transaction history | 2/min | -- |
| `/api/v4/account/sendernames` | GET | Registered sender names | 2/min | -- |
| `/api/v4/account/users` | GET | Account users | 2/min | -- |

Critical correctness rules that are easy to get wrong:
1. **Never send OTP codes through `/api/v4/messages`.** OTP traffic has its own dedicated, unrate-limited route (`/api/v4/otp`) specifically so verification codes aren't delayed behind regular traffic. If a codebase is doing `message: "Your code is " + otp` against the plain messages endpoint, that's a bug to fix, not a pattern to copy.
2. **The `{otp}` placeholder is optional but preferred.** If the message body includes `{otp}`, Semaphore substitutes the generated (or custom `code=`) value there; if omitted, the code is appended to the end. Prefer an explicit placeholder so the message reads naturally.
3. **Messages over 160 ASCII characters auto-split** into multiple credit-consuming segments -- keep templates concise and mention this to the user if their template is long.
4. **A message body starting with the literal text "TEST" is silently ignored** by Semaphore. Don't let a test fixture or default template accidentally start with that word.
5. **Up to 1,000 comma-separated numbers per call** for standard sends -- for larger recipient lists, chunk into multiple calls rather than one oversized request.
6. **Respect the 120/min (standard) and 30/min (retrieval) limits.** Use `X-RateLimit-Remaining` / `Retry-After` response headers to back off; don't hammer the API in a tight loop for bulk jobs -- batch and pace requests.

## Step 3: Implement (matching the codebase's own style)

Build a single small client/service wrapping the Semaphore endpoints actually needed for the task (don't implement all 8 endpoints if the task only needs OTP). At minimum it should:

- Read the API key from config/env (never hardcode it, never commit it).
- Expose one method per capability actually used (e.g. `sendSms()`, `sendOtp()`, `sendPriority()`).
- Treat the HTTP call as fallible: network errors, non-2xx responses, and a `status: "Failed"` in a 2xx JSON body are all failure cases that need handling -- don't assume success just because the HTTP call didn't throw.
- Never log the OTP code or the API key. Log message IDs and statuses, not secrets or verification codes.
- If the project sends OTPs, keep the *comparison* of the user-entered code against the sent code on the server side, with expiry -- Semaphore returns the code it sent, but the calling app is responsible for verification logic, rate-limiting verification attempts, and not trusting a client-supplied "verified" flag.

See `references/integration-patterns.md` for stack-specific implementation patterns (PHP/Laravel, Node.js, Python, Ruby, .NET) built from Semaphore's own examples but adapted into a proper client/service class with error handling.

## Step 4: If auditing an existing integration

Work through `references/security-and-audit-checklist.md`. In short: find every place `apikey`/`SEMAPHORE` appears, confirm it's never a literal string in source; confirm OTP traffic uses `/otp` not `/messages`; confirm failures (bad HTTP status, `status: "Failed"` in the body, thrown exceptions) are actually handled rather than swallowed; confirm OTP codes aren't logged or returned to any client-facing response/log; confirm bulk sends chunk at 1,000 recipients and don't blow past 120 calls/minute; confirm credit balance is checked or monitored somewhere so sends don't silently fail once credits run out.

## Step 5: Verify

After implementing, do a final pass: does the new code follow the same file layout, naming, and error-handling idioms as the rest of the codebase? Would a message-length edit or a wrong-endpoint OTP call have been caught by a test? If tests exist elsewhere in the project, add one for the new integration using the same mocking approach already in use.
