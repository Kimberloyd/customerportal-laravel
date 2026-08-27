# Semaphore API Reference

Base URL: `https://api.semaphore.co` (also accessible at `https://semaphore.co`, both work interchangeably in Semaphore's own examples). Prefer `https://api.semaphore.co` for new code.

All endpoints authenticate via an `apikey` parameter sent with the request (POST body for sends, query string for GETs) -- not a header. Treat the key like any other secret: env var / secrets manager, never hardcoded, never logged, never committed.

## POST /api/v4/messages -- Standard SMS

Rate limit: 120 calls/minute.

**Parameters**
| Param | Required | Notes |
|---|---|---|
| `apikey` | yes | account API key |
| `number` | yes | one number, or up to 1,000 comma-separated numbers |
| `message` | yes | SMS body; >160 ASCII chars auto-splits into multiple segments; must not start with the literal word "TEST" (silently ignored) |
| `sendername` | no | defaults to the account's registered sender name |

**Example**
```
curl --data "apikey=YOUR_API_KEY&number=09998887777&message=I just sent my first message with Semaphore" https://api.semaphore.co/api/v4/messages
```

**Response** (array of message objects, one per recipient)
```json
[
  {
    "message_id": 123456,
    "user_id": 1,
    "user": "user@example.com",
    "account_id": 1,
    "account": "Account Name",
    "recipient": "639998887777",
    "message": "I just sent my first message with Semaphore",
    "sender_name": "SEMAPHORE",
    "network": "Globe",
    "status": "Queued",
    "type": "single",
    "source": "api",
    "created_at": "2024-01-01 12:00:00",
    "updated_at": "2024-01-01 12:00:00"
  }
]
```
`status` progresses through `Queued` -> `Pending` -> `Sent` (or `Failed` / `Refunded`). A 2xx HTTP response does NOT guarantee delivery -- it guarantees the message was accepted for queueing. Failure/refund states must be checked, either by polling GET /messages/{id} or handling webhooks/status callbacks if configured on the account.

## GET /api/v4/messages -- Retrieve sent messages

Rate limit: 30 calls/minute.

**Query parameters**
| Param | Notes |
|---|---|
| `apikey` | required |
| `limit` | default 100, max 1000 |
| `page` | default 1 |
| `startDate` / `endDate` | `YYYY-MM-DD` |
| `network` | lowercase, e.g. `globe`, `smart` |
| `status` | lowercase, e.g. `pending`, `sent`, `failed` |

Single message: `GET /api/v4/messages/{id}?apikey=...`

## POST /api/v4/priority -- Priority SMS

Same parameters as `/api/v4/messages` (`apikey`, `number`, `message`, `sendername`). No rate limit. Bypasses the default queue for time-sensitive sends. Costs 2 credits per 160-character segment (vs. 1 for standard). Use for things like transactional alerts where delay defeats the purpose -- not as a default for all traffic (it's 2x the cost).

## POST /api/v4/otp -- OTP messages

No rate limit. Dedicated route for one-time-password traffic so it isn't queued behind bulk/marketing sends. Costs 2 credits per 160-character segment.

**Parameters**
| Param | Required | Notes |
|---|---|---|
| `apikey` | yes | |
| `number` | yes | recipient |
| `message` | yes | include a `{otp}` placeholder; if omitted, the code is appended to the end of the message instead |
| `sendername` | no | |
| `code` | no | supply your own OTP value; if omitted, Semaphore generates one |

**Example**
```
curl --data "apikey=YOUR_API_KEY&number=MOBILE_NUMBER&message=Thanks for registering. Your OTP Code is {otp}." https://api.semaphore.co/api/v4/otp
```

**Response**: same shape as a standard message, plus a `code` field containing the OTP value that was sent (auto-generated or the custom one supplied). The calling application must persist this (or its own copy, if it supplied `code` itself) server-side with an expiry, and compare it against user input on verification -- Semaphore only sends the code, it doesn't verify it.

Do not send non-OTP marketing/notification traffic through this route -- it's priced and positioned specifically for verification codes.

## GET /api/v4/account -- Account info

Rate limit: 2 calls/minute.

Response includes `account_id`, `account_name`, `status`, `credit_balance` (1 credit = smallest chargeable unit; a 160-char standard SMS = 1 credit, OTP/priority = 2). Poll or alert on this so sends don't start silently failing once credits are exhausted.

## GET /api/v4/account/transactions -- Transaction history

Rate limit: 2 calls/minute. Params: `apikey`, `limit` (default 100, max 1000), `page` (default 1).

## GET /api/v4/account/sendernames -- Registered sender names

Rate limit: 2 calls/minute. Params: `apikey`, `limit`, `page`. Returns `name`, `status`, `created_at` per sender name. A `sendername` used in a send request must be one of these approved/registered names, or the default is used.

## GET /api/v4/account/users -- Account users

Rate limit: 2 calls/minute. Params: `apikey`, `limit`, `page`. Returns `user_id`, `email`, `role`, `status` per user on the account.

## Rate limit handling

Rate-limited responses include standard headers:
- `X-RateLimit-Limit`
- `X-RateLimit-Remaining`
- `Retry-After`

A client wrapper should read `Retry-After` on a 429 and back off rather than retrying immediately or failing the whole batch. For bulk sends, prefer batching numbers into the 1,000-per-call limit over looping single-recipient calls, since that both respects the 120/min ceiling and reduces credit-per-call overhead.

## Credits and cost quick reference

- Standard message: 1 credit per 160 ASCII characters (auto-split beyond that).
- Priority message: 2 credits per 160 characters.
- OTP message: 2 credits per 160 characters.
