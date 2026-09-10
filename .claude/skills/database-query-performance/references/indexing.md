# Indexing columns used in WHERE/JOIN/ORDER BY

The fix is systematic, not reflexive: identify every column actually used to filter, join, or sort, then index those -- not every column in the table.

## Laravel / Eloquent

**Before (no index -- full table scan on every lookup):**

```php
// users table has no index on email beyond the primary key
$user = User::where('email', $email)->first();
```

```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id');   // used in WHERE and JOIN, but not indexed
    $table->string('status');       // used in WHERE, not indexed
    $table->timestamp('created_at'); // used in ORDER BY, not indexed
});
```

**After -- a migration that indexes the columns the app's queries actually use:**

```php
Schema::table('users', function (Blueprint $table) {
    $table->unique('email'); // unique() also creates an index; use index() if not unique
});

Schema::table('orders', function (Blueprint $table) {
    $table->index('user_id');               // for WHERE user_id = ? and JOIN ... ON orders.user_id
    $table->index('status');                // for WHERE status = ?
    $table->index(['user_id', 'created_at']); // composite: for "this user's orders, newest first"
});
```

Column order in a composite index matters: `['user_id', 'created_at']` serves `WHERE user_id = ? ORDER BY created_at DESC` efficiently; it does not equally serve a query that filters by `created_at` alone.

**Verify with `EXPLAIN`:**

```php
$plan = DB::select('EXPLAIN ' . Order::where('user_id', 5)->orderByDesc('created_at')->toSql(), [5]);
```

Or run the equivalent raw `EXPLAIN SELECT ...` in `php artisan tinker` / your DB client. Look for the query moving from a full/sequential table scan (`ALL` in MySQL, `Seq Scan` in Postgres) to an index-based access (`ref`/`range` in MySQL, `Index Scan`/`Index Only Scan` in Postgres).

## Raw SQL (any relational DB)

```sql
-- Identify: which columns does this query filter/join/sort on?
SELECT * FROM orders WHERE user_id = 5 AND status = 'pending' ORDER BY created_at DESC;

-- Index those columns (composite index matching filter + sort together):
CREATE INDEX idx_orders_user_status_created ON orders (user_id, status, created_at);

-- Verify the plan changed:
EXPLAIN ANALYZE SELECT * FROM orders WHERE user_id = 5 AND status = 'pending' ORDER BY created_at DESC;
```

## Django

```python
class Order(models.Model):
    user = models.ForeignKey(User, on_delete=models.CASCADE, db_index=True)  # FK is indexed by default, but confirm
    status = models.CharField(max_length=20, db_index=True)
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        indexes = [
            models.Index(fields=['user', '-created_at']),  # composite, matches filter+sort together
        ]
```

Verify with `Order.objects.filter(user=u, status='pending').order_by('-created_at').explain()`.

## Testing before deploying

1. Run `EXPLAIN`/`EXPLAIN ANALYZE` on the actual query, with realistic data volume (an index's benefit is invisible on a nearly-empty dev table) -- confirm the plan uses the new index.
2. Check write-path impact: an index that's rarely read but present on a high-write table (e.g. an audit log insert-heavy table) adds overhead to every insert/update -- weigh read benefit against write cost for that specific table's traffic pattern.
3. Revisit when a new filter/sort is added to an existing endpoint -- indexing isn't a one-time pass over the schema, it needs to track the queries the app actually runs as they change.
