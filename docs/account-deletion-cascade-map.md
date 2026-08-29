# Account deletion and erasure map

This document is the required decision record for every persisted record that
can identify or reference a portal account. It must be updated whenever a new
user-linked table, file, external processor, or backup system is introduced.

## Lifecycle

1. An administrator schedules deletion.
2. Sign-in is blocked immediately, the session version changes, all current
   sessions and password-reset tokens are removed, and the user row is soft
   deleted.
3. The account remains recoverable for `ACCOUNT_DELETION_RETENTION_DAYS`
   (30 by default). Administrators can restore it from the Accounts table.
4. `accounts:purge-deleted` permanently erases due accounts. Laravel schedules
   it daily at 02:00. The production Compose stack runs `schedule:work`; other
   deployments must invoke `php artisan schedule:run` every minute.

`DATA_ERASURE_DEADLINE_DAYS` tracks the administrative deadline for formal
export/erasure requests. The defaults are product configuration, not legal
advice; confirm the applicable deadline and retention requirements with
counsel for every deployment jurisdiction.

## Cascade decisions

| Store | Account relationship | Decision at request | Decision at purge | Reason |
|---|---|---|---|---|
| `users` | Primary profile and credentials | Soft delete; deactivate; increment session version | Hard delete | Personal account data |
| `sessions` | `user_id` and session payload | Delete immediately | Delete again defensively | Revokes access and removes device/session data |
| `password_reset_tokens` | Email and secret token | Delete immediately | Delete again defensively | Credential material has no retention value |
| `login_attempts` | Email and IP address | Retain during recovery window | Delete by exact account email | Security data containing personal identifiers |
| Managed public profile image | `users.profile_image` | Retain during recovery window | Delete safe local path | User-uploaded personal file |
| `customers` | Nullable `user_id` | Keep link during recovery window | Set `user_id` to null; retain row | Customer is an inventory/business entity independent of portal login |
| `purchase_orders` | Indirect through customer | Retain | Retain | Business/fulfilment record |
| Purchase-order attachments | Indirect through order | Retain | Retain | Part of the retained business record |
| `purchase_order_items` | Indirect through order | Retain | Retain | Business/fulfilment record |
| `purchase_order_audits` | Nullable `actor_user_id`, IP | Retain | Null actor and actor IP; retain event | Operational audit evidence without account identity |
| `admin_audits` | User entity or nullable actor | Retain | Null subject/actor IDs and IP; replace user-detail text | Security audit evidence without personal identifiers |
| `customer_messages` | Nullable `assigned_user_id`; indirect customer conversation | Retain | Null assignment; retain conversation | Customer support/business correspondence |
| `public_conversation_reply_attempts` | Indirect through message | Retain | Retain with message | Abuse-control record; no direct user link |
| `data_subject_requests` | Nullable subject/requester plus HMAC reference | Create/update | Null FKs; retain HMAC reference and result counts | Durable proof that the request was completed |
| Cache and queued jobs | No durable user schema today | No action | No action | Reassess when user IDs enter payloads |
| Application/database backups | May contain historic rows | Govern outside app | Expire under backup policy; do not selectively restore erased rows | Operational process outside transactional database |

## Data report and formal erasure

- `GET /admin/users/{id}/data-export` downloads a JSON report containing the
  profile, linked customer records, orders/items, assigned conversations,
  login history, and relevant audit records. Password hashes, reset tokens,
  session payloads, and public bearer-token hashes are excluded.
- `DELETE /admin/users/{id}/erase-now` performs immediate permanent erasure.
  It requires the exact account name in `confirmation` and is rate limited.
- Both operations create a `data_subject_requests` record with a stable HMAC
  subject reference, requester, timestamps, deadline, status, and outcome.

## External systems and operational checks

- Customer and product data come from the inventory API. Portal deletion does
  not delete those independent business records.
- Facebook Messenger conversation data is retained as business correspondence
  and detached from the deleted staff assignment.
- Before production launch, document database/object-storage backup retention,
  restore suppression for erased accounts, log aggregation retention, and any
  additional processors. Those systems cannot be erased by this code alone.
- Monitor failed scheduled commands and overdue rows in
  `data_subject_requests`; an unmonitored purge job is not a deletion process.
