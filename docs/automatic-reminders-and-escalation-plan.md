# Automatic reminders and escalation plan

## Outcome

Add scheduled, durable follow-ups for orders and returns that have stopped moving. The system will notify the person who can take the next action, escalate unresolved work after a configurable delay, and preserve an audit trail without changing an order or return decision automatically.

This plan deliberately uses the existing notification bell, `notifications` queue, Semaphore SMS client, Reverb user channels, order message history, scheduler container, and admin Settings page. It does not add an operational dashboard.

## Current system constraints confirmed in the code

- Order states are `submitted`, `partial`, `processing`, `completed`, `cancelled`, and `returned`.
- `processing` means every ordered unit has been delivered and the customer can close the order.
- Return states are `requested`, `approved`, `rejected`, and `received`.
- `completed` and `cancelled` are terminal order states.
- The scheduler and Redis-backed `notifications` worker already run in local and production Compose.
- `PurchaseOrderNotification` records portal, email, SMS, and Facebook delivery attempts, but it has no reminder rule, recipient-user, due date, or idempotency key.
- Portal notifications currently use one global row per order event. Customer visibility is order-scoped; staff can access all customers. Agent team membership must not limit reminder visibility or order access.
- Semaphore is already wrapped by `SemaphoreSms`, and SMS can be disabled at runtime by an administrator.
- Reverb already broadcasts on authenticated private `users.{userId}` channels.

## Recommended reminder policy

All thresholds will be administrator-configurable. These are the initial defaults.

| Work waiting | First reminder | Escalation | Initial recipient | Escalation recipient | Channels |
| --- | ---: | ---: | --- | --- | --- |
| Submitted order with no delivery | 24 hours after submission | 72 hours | Active assigned agent; otherwise active office users | Active office users and administrators | Portal |
| Partial order with no further delivery | 48 hours after the latest fulfillment change | 96 hours | Active assigned agent; otherwise active office users | Active office users and administrators | Portal |
| Fully delivered order waiting for customer closure (`processing`) | 24 hours, then once more at 72 hours | 7 days | Linked active customer account | Active office users and administrators | Customer portal and optional SMS; staff portal on escalation |
| Return waiting for review (`requested`) | 24 hours after request | 48 hours | Active assigned agent; otherwise active office users | Active office users and administrators | Portal |
| Approved return not recorded as received | 3 days after approval | 7 days | Linked active customer account and assigned agent | Active office users and administrators | Customer portal and optional SMS; staff portal |

Rules:

- Use `Asia/Manila` for business-time calculations and display, while storing timestamps in UTC.
- Send customer SMS only from 08:00 through 20:00 local time. A reminder that becomes due outside that window waits until the next window.
- Never include product names, return reasons, attachments, phone numbers, or other sensitive details in reminder SMS. Include the PO number and a portal action only.
- Never change order quantities, order status, return status, or receipt confirmation automatically.
- A normal lifecycle update resolves the applicable follow-up immediately. A later regression to an actionable state creates a new follow-up cycle rather than reopening an already sent cycle.
- Pausing reminders stops new dispatches but keeps due state and history.

## Data model

### `order_follow_ups`

Create a durable state table rather than calculating everything from notification text or mutable `purchase_orders.updated_at` values.

Suggested columns:

- `id`
- `purchase_order_id` with an index and cascade behavior matching existing order history policy
- nullable `product_return_id` for return-specific follow-ups
- `kind`: `awaiting_fulfillment`, `stalled_partial`, `awaiting_customer_close`, `return_review`, or `return_receipt`
- `cycle` integer, incremented if the same order later enters the same actionable condition again
- `triggered_at`, `next_due_at`, `last_dispatched_at`, nullable `resolved_at`
- `level`: `reminder`, `repeat`, or `escalation`
- `status`: `pending`, `dispatching`, `resolved`, or `paused`
- `attempt_count` and nullable `last_error_at`
- timestamps

Add a unique key on `(purchase_order_id, kind, cycle)` and indexes covering `(status, next_due_at)` and `(product_return_id, status)`. Do not use human-readable audit action strings as scheduling keys.

### Notification delivery ledger

Extend `purchase_order_notifications` with nullable, backward-compatible fields:

- `event_key` such as `reminder.awaiting_customer_close`
- `recipient_user_id`, null for historical broad notifications
- `follow_up_id`
- `level`
- `dedupe_key` with a unique index

One row continues to represent one channel attempt to one recipient. The unique dedupe key must include the follow-up cycle, level, recipient, and channel so repeated scheduler runs or concurrent workers cannot send the same reminder twice.

Existing rows remain valid and do not need backfilling.

## Application design

### 1. Policy and settings

Add `config/reminders.php` with environment defaults for the global enabled flag, thresholds, timezone, quiet hours, and allowed channels. Store administrator overrides through the existing `AppSetting` model.

Add a **Reminders and escalation** card to the current administrator-only notification settings area. It should provide:

- global enable/pause switch
- numeric thresholds with bounded validation
- customer SMS switch that also reflects whether Semaphore is configured and enabled
- quiet-hour controls
- a read-only "last scheduler run" and most recent failure summary

Every settings change must create an `AdminAudit` record. Office, agent, and customer accounts must receive `403` for these update endpoints even if they submit requests directly.

### 2. Follow-up lifecycle service

Create a focused service, for example `OrderFollowUpManager`, called after successful order and return transactions:

- order created: open `awaiting_fulfillment`
- first delivery: resolve `awaiting_fulfillment`; open `stalled_partial` when a balance remains
- later partial delivery: replace the partial trigger time and schedule with the new fulfillment activity time
- full delivery: resolve fulfillment follow-ups; open `awaiting_customer_close`
- customer closes the order: resolve `awaiting_customer_close`
- cancellation or archival: resolve every open follow-up for the order
- return requested: open `return_review`
- return approved or rejected: resolve `return_review`; approval opens `return_receipt`
- returned products received: resolve `return_receipt`

Run these changes inside the same database transaction as the lifecycle mutation. Dispatch notifications after commit.

Also add a reconciliation command that repairs missing or stale follow-up rows from current order/return state. This is a safety net for interrupted deployments and imported data, not the primary trigger mechanism.

### 3. Scheduler and queue

Add an `orders:dispatch-follow-ups` Artisan command and schedule it every five minutes with `withoutOverlapping()` and `onOneServer()`.

The command should:

1. Exit cleanly when globally paused.
2. Select due records in indexed, bounded chunks.
3. Claim each record atomically in a short database transaction.
4. Re-read the order or return and verify the condition still applies.
5. Resolve stale work without sending anything.
6. Dispatch one queued job per valid follow-up after commit.

The queued job should use the existing `notifications` queue, bounded retries, exponential backoff, and a timeout shorter than the worker timeout. Immediately before each send it must create or claim the dedupe key transactionally. Provider failures should mark only that channel attempt failed and leave the business action untouched.

The existing queue heartbeat remains the source of worker readiness. An unavailable external SMS provider must not make `/health/ready` fail, because portal reminders can still work and an external outage should not remove the whole application from service.

### 4. Recipients and authorization

Recipient resolution must be explicit:

- Customer reminders go only to the one active user linked to the order's active customer.
- First-line staff reminders prefer the customer's active `assigned_employee_id` when that user is an agent.
- If no active assigned agent exists, notify active office users.
- Escalations notify active office users and administrators.
- Team membership does not filter orders or reminders. It may be shown as context later, but it is not an authorization boundary.
- Do not send duplicate copies when one user qualifies through more than one recipient rule.

Update `OrderNotificationFeed` to honor `recipient_user_id` when present while preserving the behavior of historical rows where it is null. A customer must never see staff escalation copy, and an agent must not receive a user-targeted escalation intended for office/admin accounts.

Broadcast a small `purchase-order.changed` event to the selected authenticated user channels after portal rows are committed. The browser can then refresh the notification bell and affected order data through the existing hooks. Do not include message contents or contact details in the broadcast payload.

### 5. User-facing copy

Use short, action-oriented portal messages:

- Assigned agent: `Order {PO} has been waiting 24 hours for its first delivery. Open the order and record an update.`
- Office/admin escalation: `Order {PO} is still waiting for fulfillment after 72 hours. Assign or follow up with the responsible staff member.`
- Customer closure: `Order {PO} has been fully delivered. Review it and close the order when everything is correct.`
- Return review: `Return request for order {PO} has been waiting 24 hours for review. Open the request and record a decision.`
- Return receipt: `The approved return for order {PO} is still open. Coordinate the return and record it when received.`

Customer SMS should stay within one segment where practical:

- `Order {PO} is fully delivered. Please review and close it in the Theomeds customer portal.`
- `Your approved return for order {PO} is still open. Please check the Theomeds customer portal for the next step.`

The notification link must open the relevant order. Staff copy can name the customer company in the UI after authorization; SMS should not.

### 6. Visibility and audit trail

- Show reminder and escalation attempts in the existing order message log with channel, recipient display name, outcome, and time.
- Add a compact staff-only follow-up status on the order page: next due time, current level, and resolved time. Do not create a new dashboard.
- Record structured logs with a generated correlation ID, follow-up ID, order ID, rule, level, channel, and result. Do not log API keys, full phone numbers, message bodies, return reasons, or attachment names.
- Retain failed jobs in the existing `failed_jobs` table and expose the latest scheduler/failure summary in admin Settings.

## Idempotency and race-condition requirements

- Running the scheduler repeatedly at the same timestamp sends exactly one copy per channel and recipient.
- Two scheduler processes claiming the same row still produce one dispatch.
- If fulfillment, closure, cancellation, return review, or return receipt happens after a job is queued but before it runs, the job exits without sending.
- Retried SMS jobs reuse the same logical dedupe key and cannot create another successful send after a previous attempt succeeded.
- Updating unrelated remarks does not reset a fulfillment inactivity timer.
- Importing or deploying against old open orders does not immediately blast users. The reconciliation migration/command schedules pre-existing work with a configurable rollout grace period, recommended at 24 hours.

## Test plan

### Domain and scheduling tests

- Each lifecycle transition opens, refreshes, or resolves the correct follow-up.
- Frozen-time tests prove every default threshold and the Manila quiet-hours boundary.
- Terminal and archived orders never produce reminders.
- Requested and approved return flows use separate timers.
- Reconciliation is repeatable and does not create duplicate cycles.

### Recipient and security tests

- Linked customers receive only customer reminders for their own orders.
- Assigned agents receive the initial staff reminder, with office fallback when assignment is absent or inactive.
- Office/admin accounts receive escalations; unrelated roles do not.
- Teams do not restrict visibility.
- Only administrators can update reminder settings.
- API keys, full phone numbers, return reasons, and attachments are absent from responses, broadcasts, and logs.

### Queue and provider tests

- `Http::fake()` verifies exact Semaphore endpoint use, concise message content, failure handling, and zero SMS calls while disabled.
- Repeated command runs, concurrent claims, and job retries remain idempotent.
- A state change between queueing and handling suppresses the stale send.
- Portal delivery still succeeds when Semaphore is unavailable.
- Scheduler and worker health remain observable without treating Semaphore downtime as application unavailability.

### Regression gates

- Run the focused reminder, notification, order, return, settings, Reverb, and reliability tests.
- Run the complete PHP test suite, Laravel Pint, frontend production build, both Compose config validations, and `php artisan schedule:list`.

## Delivery sequence

1. **Schema and domain state:** add follow-up state, notification targeting/deduplication, models, factories, and lifecycle tests.
2. **Lifecycle wiring:** update order and return transactions, then add reconciliation and race-condition coverage.
3. **Dispatcher:** add the command, queue job, quiet-hour handling, idempotent claims, logs, and provider tests.
4. **Targeted notifications:** update the feed and Reverb recipient broadcasts, then verify role isolation.
5. **Admin controls and order visibility:** add settings, audit entries, message-log fields, and staff-only follow-up status.
6. **Production rollout:** deploy migrations with sending disabled, run reconciliation in report-only mode, review recipient counts, enable portal reminders, then enable customer SMS after message and credit checks.

## Production rollout and rollback

Deployment starts with reminders disabled. After migration:

1. Run reconciliation in dry-run mode and capture counts by rule and level.
2. Run reconciliation with the 24-hour rollout grace period.
3. Confirm scheduler and `notifications` worker health for at least two scheduler cycles.
4. Enable portal reminders only and verify targeted bell notifications with one test order per rule.
5. Confirm Semaphore credits and sender name, then enable customer SMS.
6. Watch failed jobs, provider failures, duplicate-prevention metrics, and send volume during the first 48 hours.

Rollback is the administrator pause switch first. Queue jobs must check that switch at execution time, so already queued reminders stop without deleting history. Application rollback can then proceed while leaving the additive tables and nullable notification columns in place; schema rollback should happen only after the old application is stable and the new records are exported or no longer needed.

## Definition of done

- Every listed workflow opens and resolves follow-up state at the correct lifecycle point.
- Due reminders and escalations reach only the intended active users.
- Duplicate scheduler runs, concurrent workers, and retries cannot duplicate a send.
- Stale queued work cannot notify after the required action is completed.
- Administrators can pause and configure the system and can inspect failures.
- Portal reminders work independently of Semaphore; SMS is optional and quiet-hour aware.
- No business status changes automatically.
- Existing access rules, Reverb authorization, notification history, order flows, and the previously implemented security/reliability checks continue to pass.
