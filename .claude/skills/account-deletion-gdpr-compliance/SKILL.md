---
name: account-deletion-gdpr-compliance
description: "Implements and audits account deletion as a real business process, not a button: (1) a cascade map of every table/collection touching the user (orders, messages, uploads, payments, sessions, support tickets) with a decided fate for each -- cascade delete, anonymize, or retain; (2) soft delete on request (immediate deactivation, data retained for a 30-day window) with automatic hard delete after; (3) a GDPR-compliant erasure flow producing a full data report on demand and confirming removal within the compliance deadline. Use whenever building or reviewing account/user deletion, 'delete my account', right-to-erasure/right-to-be-forgotten features, or GDPR/CCPA deletion requests. Also trigger if a codebase has login/signup but no deletion flow, deletion just does a naive DB cascade without deciding what's retained/anonymized, or the user mentions compliance exposure, orphaned records after a user is removed, or a data subject deletion request."
---

# Account Deletion & GDPR Compliance

A login system is not a deletion system. Most codebases build sign-up, auth, and profile management long before anyone thinks through what "delete my account" actually has to do -- and by the time a real deletion request (or a GDPR erasure request with a legal clock attached) arrives, it's too late to design this properly under pressure.

Deletion is a business process with three parts: know what data exists and what should happen to it, delete safely with a recovery window instead of an irreversible instant wipe, and be able to prove compliance on demand within a legal deadline.

## Step 1: The cascade map

Before writing any deletion code, map every table/collection that has a foreign key, reference, or otherwise touches the user being deleted. Typical categories to look for: orders/transactions, messages/comments, uploads/media, payment history and billing records, session/auth tokens, support tickets, audit logs, analytics events, third-party integration records (webhooks, API keys), and anything with a `user_id`/`created_by`/`owner_id`-style reference.

For every one of these, an explicit decision is required -- "we'll figure it out later" is not an option once a real deletion request is in flight:

- **Cascade delete**: remove it entirely along with the user (e.g. draft content only the user could see, session tokens).
- **Anonymize/detach**: keep the record but strip or replace the personal identifier (common for financial/transactional records that other parties or legal/audit requirements need to keep -- e.g. an order stays in the ledger but the buyer becomes "Deleted User").
- **Retain**: keep as-is for a defined legal/business reason (e.g. records required for tax law, active disputes, fraud investigations) -- with a documented reason and, ideally, an eventual retention expiry.

Read `references/cascade-mapping.md` for a full worked example and a mapping-table template to fill in per project.

If asked to review an existing deletion implementation, the first audit question is always: does a cascade map exist anywhere (as a doc, a comment, a config), or did the deletion logic get built ad hoc, one `ON DELETE CASCADE` at a time, without anyone deciding what *should* happen to each table? A codebase with `ON DELETE CASCADE` wired reflexively on every foreign key, with no anonymize/retain path anywhere, is a common real-world failure mode -- it silently destroys records (an order history, a payment record) that should have been anonymized and kept, not deleted.

## Step 2: Soft delete with a retention window

On a deletion request:

1. **Immediately deactivate** the account -- the user can no longer log in, the profile disappears from the application, other users can no longer see/interact with it.
2. **Retain the underlying data for a fixed window** (30 days is the doc's reference point, and a reasonable default absent other legal requirements) so there's room for a compliance review, fraud check, or the account owner changing their mind, before anything is unrecoverable.
3. **Automatic hard delete after the window**, with no manual cleanup step required -- a scheduled job/cron, not a runbook someone has to remember to run. See `references/soft-delete-implementation.md` for schema and job patterns across common stacks.

Getting this backwards -- either hard-deleting instantly (no recovery window, no room to catch a mistake or fraud) or soft-deleting indefinitely (data lingers forever with no automatic hard-delete job, which is itself a compliance problem) -- are both common implementation gaps worth flagging in an audit.

## Step 3: The GDPR erasure response

A GDPR (or similar regional privacy law) erasure request has a hard clock attached -- the doc cites 72 hours as the compliance window to work toward; verify the actual applicable deadline for the relevant jurisdiction/regulation rather than assuming 72 hours universally applies (GDPR's formal outer bound for a full response is one month, but a shorter internal target like 72 hours is a reasonable operational buffer that leaves room for review). Two capabilities are required, and both need to work *before* the first real request arrives, not built reactively under legal pressure:

1. **A data report on demand**: given a user, generate a complete report of every piece of data held on them, across every system identified in the cascade map (Step 1) -- not just the primary user's-table row. If the cascade map is incomplete, the report will be too, and that's a real compliance gap.
2. **Confirmed complete removal within the window**: actually executing (not just scheduling) full erasure for that user across every table the cascade map identified as "cascade delete" or "anonymize," within the compliance deadline, with a record that it happened (who requested it, when, what was removed/anonymized) for audit purposes.

See `references/gdpr-erasure-flow.md` for what a proper data-report generator and erasure-confirmation flow look like in code, and `references/audit-checklist.md` for reviewing an existing implementation against all three steps.

## Verification

After implementing or fixing a deletion flow: actually trace a test user through it end to end (create test data across every table in the cascade map, trigger deletion, verify each table's data ends up in its decided state -- cascaded, anonymized, or retained -- both immediately and after the retention window elapses) rather than trusting that the code "should" work from reading it.
