# Soft Delete + Retention Window Implementation

## Schema pattern

Whatever the stack, the core fields needed on the primary user record (and any other table getting the same treatment):

```
deactivated_at   TIMESTAMP NULL   -- set on deletion request; null = active
purge_after      TIMESTAMP NULL   -- deactivated_at + retention window (e.g. 30 days)
deletion_reason  TEXT NULL        -- optional: user-initiated, admin action, GDPR request, etc.
```

On deletion request: set `deactivated_at = now()`, `purge_after = now() + 30 days`, and immediately enforce deactivation everywhere the user's active status matters (login, visibility to other users, API access via existing tokens -- revoke those too, per the cascade map's `sessions` entry).

## Enforcing deactivation immediately

A soft-deleted user must actually be locked out and hidden, not just flagged in the DB while the app keeps treating them as active:

- Auth/login checks must reject a deactivated user (check `deactivated_at IS NULL` as part of the login/session-validation path, not just at signup).
- Existing sessions/tokens should be revoked at deactivation time, not left valid until they naturally expire.
- Any query that lists/displays users to others (search, mentions, public profiles) must filter out deactivated accounts.

## The automatic hard-delete job

This must be a real scheduled job, not documentation of an intention:

**Laravel**: a scheduled command (`php artisan schedule` entry) running daily, querying `User::whereNotNull('deactivated_at')->where('purge_after', '<=', now())`, and running the actual hard-delete/anonymize logic per the cascade map for each matching user, inside a transaction.

**Node/generic**: a cron job (node-cron, a scheduled Lambda/Cloud Function, a queue worker on a timer) running the equivalent query and purge logic daily.

**Django**: a management command invoked via cron/Celery beat, same query pattern against the ORM.

**Rails**: a scheduled job (Sidekiq-cron, `whenever` gem, or a platform scheduler) calling a `PurgeDeletedUsersJob`.

Whatever the mechanism, verify it's actually wired to run on a schedule -- a purge job that exists as code but was never added to the crontab/scheduler config is a common gap, and looks identical to a working implementation on a code read-through alone.

## Handling the purge itself

The purge step should execute the *same* cascade-map decisions as an immediate/manual deletion (Step 1's map), not a separate, potentially-inconsistent code path -- ideally both the "purge after 30 days" job and any "delete immediately" admin/GDPR-triggered path call the same underlying erasure function, so there's one source of truth for what gets cascaded, anonymized, or retained.

## Audit signals for an existing implementation

- No `deactivated_at`/equivalent field at all -- deletion is either instant hard-delete or doesn't really exist.
- The field exists but nothing actually checks it at login/visibility time -- a "soft-deleted" user can still log in.
- No scheduled job references the purge window -- data marked for deletion never actually gets purged (a silent, unbounded retention problem, and itself a compliance gap).
- The manual/immediate deletion path and the scheduled purge job implement the cascade logic differently, so which fields get cascaded/anonymized/retained depends on which path triggered the deletion -- a correctness and audit-trail problem.
