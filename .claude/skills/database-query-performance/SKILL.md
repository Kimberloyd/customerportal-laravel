---
name: database-query-performance
description: "Implements and audits database query performance using a 3-part pattern: (1) indexing every column actually used in WHERE/JOIN/ORDER BY clauses, identified systematically rather than guessed, and tested before deploying; (2) selecting only the columns a query actually needs instead of SELECT * / fetching whole models, cutting unnecessary server processing and network transfer; (3) turning on slow-query logging and auditing queries against a time threshold so problems are caught before the database is overloaded and the host throttles or the app times out. Use when writing or reviewing database queries, migrations, or Eloquent/ORM models -- especially AI-generated queries, which commonly fetch all columns with no indexing plan and no slow-query visibility. Trigger when adding a query that filters/joins/sorts by a column, reviewing an app for slow page loads or database load, or asked about missing indexes, full table scans, SELECT *, over-fetching, or slow query logs."
---

# Database Query Performance

## The problem this fixes

AI-generated backends commonly produce queries that "work" -- they return the right data -- but the database has to do far more work than necessary to answer them. A query filtering or sorting by a column with no index forces a full table scan: the database equivalent of reading every book in a library to find one title, instead of looking it up in the catalog. A query that does `SELECT *` (or an ORM call that hydrates a full model) fetches every column even when the page only displays three of them, wasting server processing and network transfer on every request. And without visibility into which queries are actually slow, none of this gets noticed until the database is working many times harder than it needs to and the hosting provider throttles the app or a page starts timing out.

None of these show up as bugs in normal testing -- the app works, the data is correct, it's just slow, and it gets slower as the data grows. This needs three complementary fixes.

## Step 1: Index every column actually used in WHERE/JOIN/ORDER BY

- Direct your AI to systematically identify which columns are used in filtering (`WHERE`), joining (`JOIN ... ON`), and sorting (`ORDER BY`) across the app's actual queries -- not to guess or add indexes reflexively to every column.
- Add an index (or a composite index, when a query consistently filters/sorts on more than one column together) for each one identified.
- Test this before it ships to production: an index speeds up reads but adds overhead to every write (insert/update/delete) on that table, and a composite index's column order matters for which queries it actually helps. Confirm with `EXPLAIN`/`EXPLAIN ANALYZE` (or your ORM's equivalent) that the query plan changed from a full table/sequential scan to an index scan.
- Revisit this as the app grows -- a new filter or sort added to an existing endpoint later needs the same treatment, not just the queries that existed when indexes were first added.

See `references/indexing.md` for concrete migration examples (Laravel/Eloquent, Django, raw SQL) and how to read `EXPLAIN` output.

## Step 2: Select only the columns a query actually needs

- Audit queries for unnecessary columns: a `SELECT *` (or an ORM call that hydrates every column of a model) when the caller only uses a handful of fields means every unused column still gets read from disk, sent over the network, and deserialized, on every single request.
- Direct your AI to restrict the selected columns to what's actually used by the response/view -- explicit column lists (`SELECT id, name, price`) or the ORM's column-selection method, not the full row by default.
- This matters most on high-traffic list/index endpoints (feeds, dashboards, paginated tables) where the same over-fetch happens on every request, and on tables with large or heavy columns (long text fields, JSON blobs, binary data) that are especially wasteful to fetch and discard.
- Don't over-apply this to the point of triggering extra round-trips (e.g. selecting so narrowly that a second query is needed for a field you'll need anyway) -- the goal is not fetching what the response doesn't use, not minimizing column count at any cost.

See `references/column-selection.md` for concrete examples (Laravel/Eloquent `select()`, Django `.only()`/`.values()`, raw SQL).

## Step 3: Turn on slow-query logging and audit against a threshold

- Every major database has built-in query logging (MySQL's slow query log, Postgres's `log_min_duration_statement`, Laravel's query log / `DB::listen`, etc.) -- direct your AI to enable it and set a time threshold appropriate to the app (e.g. queries over 100-500ms, tuned to your actual latency budget).
- Audit what the logging surfaces: which queries exceeded the threshold, and how often -- a query that's occasionally slow under load is a different problem than one that's always slow.
- This should be an ongoing practice, not a one-time check -- new queries and data growth both change what's slow over time. Set this up before performance becomes a production incident (a throttled host, a timing-out page), not after.
- Pair slow-query findings with `EXPLAIN`/`EXPLAIN ANALYZE` on the flagged queries to figure out whether the fix is Step 1 (missing index), Step 2 (over-fetching), or something else (an N+1 pattern, a genuinely expensive aggregation).

See `references/slow-query-logging.md` for concrete setup examples (Laravel, MySQL, Postgres) and how to triage what the log surfaces.

## The core principle

A query that returns correct data isn't necessarily a cheap query. Direct your AI to treat "does the database have to scan the whole table," "does this fetch more than the response needs," and "do we actually know which queries are slow" as first-class questions when writing or reviewing any query -- not just "does it return the right rows."
