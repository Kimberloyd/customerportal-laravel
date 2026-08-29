# GDPR Erasure Flow (Data Report + Confirmed Removal)

## Two distinct capabilities

A right-to-erasure ("right to be forgotten") request under GDPR (and structurally similar regimes -- CCPA's deletion right, etc.) requires being able to do two things on demand, both traceable to the cascade map from Step 1:

1. Produce a full report of what's held on the person.
2. Actually and completely remove/anonymize it, and be able to prove that happened.

Both need to already work before a real request arrives -- building either one reactively, under a legal deadline, is exactly the exposure the source material warns about.

## Data report generator

Walk every table identified in the cascade map and pull every record referencing the user, not just the primary profile row. A minimal implementation:

```
function generateDataReport(userId):
    report = {}
    for table in cascadeMap:  # every table from Step 1's mapping, not a hardcoded subset
        records = queryAllRecordsForUser(table, userId)
        report[table.name] = records
    return report
```

The report is only as complete as the cascade map -- if a table was missed in Step 1, it's missing here too, silently. This is the concrete reason the cascade map has to be exhaustive and kept up to date as new tables/features are added, not a one-time exercise.

Format the report for actual human/legal review (a structured JSON/PDF export a compliance reviewer can read), and log that the report was generated (who requested it, when, for which user) as part of the audit trail.

## Erasure execution and confirmation

Executing erasure means actually running the Step 1 decisions (cascade delete / anonymize / retain-with-reason) for every table, immediately -- not scheduling a 30-day soft-delete window for a request that has a 72-hour(-or-applicable) compliance deadline. A GDPR erasure request is a distinct trigger from a routine user-initiated "delete my account" click; it should bypass the standard retention window (or use a much shorter one if a brief internal grace period for reversing an erroneous request is desired) rather than defaulting to the same 30-day soft-delete path.

```
function executeErasure(userId, requestId):
    results = {}
    for table in cascadeMap:
        match table.fate:
            case CASCADE_DELETE: results[table.name] = deleteAllRecords(table, userId)
            case ANONYMIZE: results[table.name] = anonymizeAllRecords(table, userId)
            case RETAIN: results[table.name] = { skipped: true, reason: table.retentionReason }
    recordErasureConfirmation(requestId, userId, results, completedAt=now())
    return results
```

`recordErasureConfirmation` is what makes this auditable -- a durable record that a specific request was fulfilled, what was done per table, and when, independent of the underlying data itself (since that data may now be gone). This confirmation record is what gets produced if the deletion is ever challenged or audited later.

## Deadline tracking

If erasure requests come in with any volume, track the request timestamp and deadline explicitly (a `data_subject_requests` table with `requested_at`, `deadline_at`, `status`, `completed_at`) rather than relying on someone remembering to act within the window manually. Flag/alert on requests approaching their deadline unfulfilled -- this is the concrete mechanism that prevents "we missed the window" from happening silently.

## Audit signals for an existing implementation

- No way to generate a full cross-table report for a user at all -- only ad hoc manual DB queries, which don't scale and are error-prone under a deadline.
- A report generator exists but only covers a few tables (often just the primary `users` row) -- incomplete relative to the actual cascade map.
- No confirmation/audit record of past erasure requests -- if challenged later, there's no evidence of compliance.
- No deadline tracking -- requests could silently blow past the compliance window with nobody noticing until it's too late.
