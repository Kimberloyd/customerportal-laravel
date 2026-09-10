# Slow-query logging and auditing

Indexing and column selection fix problems you already know about. Slow-query logging is what tells you which queries are actually a problem in the first place -- without it, the first signal is usually a throttled host or a timing-out page, well after the database has been working far harder than it needed to.

## Laravel

```php
// AppServiceProvider::boot() -- log any query over a threshold, with bindings and timing
use Illuminate\Support\Facades\DB;

public function boot(): void
{
    DB::listen(function ($query) {
        $thresholdMs = 200; // tune to your app's actual latency budget

        if ($query->time > $thresholdMs) {
            Log::warning('Slow query', [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time_ms' => $query->time,
            ]);
        }
    });
}
```

For local debugging of a single request, Laravel's `DB::enableQueryLog()` / `DB::getQueryLog()` dumps every query run during the request -- useful for spotting N+1 patterns and repeated queries, not just individually slow ones.

Laravel also ships Telescope (`laravel/telescope`) for local/staging environments, which captures every query with timing in a browsable UI -- a good complement to production log-based monitoring, not a replacement for it (Telescope is not meant to run enabled in production under real load).

## MySQL

```sql
-- Enable the slow query log and set a threshold (seconds)
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 0.2; -- 200ms

-- Where it's written (check/set as needed):
SHOW VARIABLES LIKE 'slow_query_log_file';
```

Review with `mysqldumpslow` or by tailing the log file directly; in managed environments (RDS, PlanetScale, etc.) this is usually a dashboard setting rather than a `SET GLOBAL`.

## PostgreSQL

```sql
-- postgresql.conf, or via ALTER SYSTEM / a managed provider's config UI:
log_min_duration_statement = 200  -- log any statement taking over 200ms
```

```sql
-- Reload without a full restart:
SELECT pg_reload_conf();
```

Logged queries land in Postgres's standard log; many managed providers (RDS, Supabase, etc.) surface this as a "slow queries" panel rather than requiring direct log access.

## What to do with what it surfaces

1. Look at which queries exceed the threshold and how often -- a query that's occasionally slow under load (e.g. only during a nightly batch job) is a different problem than one that's slow on every request.
2. Run `EXPLAIN`/`EXPLAIN ANALYZE` on the flagged queries to find the actual cause -- often it traces back to Step 1 (a missing index) or Step 2 (fetching far more than needed), but can also reveal a different pattern like N+1 queries (many small queries in a loop instead of one query with eager loading) or a genuinely expensive aggregation that needs a different approach (caching, a materialized view, background pre-computation).
3. Set the threshold to something meaningful for your app, not an arbitrary default -- a dashboard with a 50ms latency budget needs a much tighter threshold than a nightly reporting job.
4. Treat this as ongoing, not a one-time setup: revisit periodically as data grows and new queries get added, since what was fast at low data volume can become slow later even with no code change.
