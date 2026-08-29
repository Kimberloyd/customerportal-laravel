# Audit Checklist

Use this when reviewing an existing account-deletion implementation. Go table by table and code-path by code-path -- don't accept a README's claim about what deletion does without checking the actual queries/migrations.

## Step 1: Cascade map

- [ ] Does a cascade map exist anywhere reviewable (doc, comment block, config), or is deletion behavior only implicit in scattered `ON DELETE CASCADE` migration clauses?
- [ ] Cross-check the map (or the de facto behavior if no map exists) against the actual schema -- grep for every `user_id`/`owner_id`/`created_by`-style foreign key and confirm each one has a decided fate. Flag any table with a user reference that isn't accounted for anywhere.
- [ ] For tables marked "anonymize," check every column for leftover indirect identifiers (a separate email/IP/name field not caught by the primary anonymization pass).
- [ ] For tables marked "retain," is there a documented legal/business reason, and ideally its own retention expiry -- or is "retain" just where things end up by default with no real justification?

## Step 2: Soft delete + retention

- [ ] Does a deactivation field/flag exist, and is it actually checked at login and at every place the user's active status matters (visibility to others, API access)?
- [ ] Are existing sessions/tokens revoked at deactivation time, not left to expire naturally?
- [ ] Does an automatic purge job exist, and is it actually scheduled to run (check the crontab/scheduler config, not just that the job's code exists)?
- [ ] Does the purge job use the same cascade-decision logic as any manual/immediate deletion path, or has the logic drifted between the two?
- [ ] Is the retention window itself defined and sane (30 days is a reasonable default) rather than either "instant hard delete, no recovery window" or "soft-deleted forever, nothing ever purges"?

## Step 3: GDPR erasure

- [ ] Can a full data report be generated on demand for a given user, and does it actually cover every table in the cascade map (not just the primary user row)?
- [ ] Does an erasure-execution path exist that runs the cascade decisions immediately (not the 30-day soft-delete window) for a compliance-triggered request?
- [ ] Is there a durable, auditable record of past erasure requests and their completion (who/when/what), independent of the now-erased data itself?
- [ ] Is there any deadline tracking/alerting for in-flight requests, or does compliance rely on someone remembering manually?
- [ ] Confirm the actual applicable deadline was verified for the relevant regulation/jurisdiction rather than assumed -- flag this as a legal question to confirm with the user/team if it's unclear, don't just assert a number.

## General

- [ ] Trace one concrete test user through the whole flow: create rows in every mapped table, trigger deletion, verify each table lands in its correct state immediately and after the retention window -- rather than confirming behavior purely by code review.
- [ ] Third-party integrations (payment processors, email providers, analytics platforms, CRMs) that hold a copy of the user's data are easy to forget -- confirm whether the cascade map accounts for anything living outside the primary database.
