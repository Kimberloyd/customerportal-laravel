# Selecting only the columns a query needs

Every unnecessary column a query returns still gets read from disk, sent over the network, and deserialized -- on every request, for every row. This is easy to miss because the app still "works" -- it's just doing more work than it needs to, and that cost scales with traffic and row count.

## Laravel / Eloquent

**Before (fetches every column, including ones the page never displays):**

```php
// Order model has 20 columns: id, user_id, status, subtotal, tax, shipping,
// internal_notes, fulfillment_provider_payload (JSON), ... but the order list
// page only ever displays id, status, and total.
$orders = Order::where('user_id', $userId)->get();
```

**After -- restrict to what the response actually uses:**

```php
$orders = Order::where('user_id', $userId)
    ->select(['id', 'status', 'total'])
    ->get();
```

For API resources, this also means not serializing fields the client doesn't need -- an `OrderResource` that only exposes `id`, `status`, `total` should be backed by a query that only selects those, not a full model fetch followed by dropping fields at serialization time.

**Eager-loaded relations should follow the same rule:**

```php
$orders = Order::with(['user:id,name'])  // only id and name from the related user, not the full user row
    ->select(['id', 'user_id', 'status', 'total'])
    ->where('user_id', $userId)
    ->get();
```

## Raw SQL

```sql
-- Before: fetches every column including large/unused ones
SELECT * FROM orders WHERE user_id = 5;

-- After: only what the caller uses
SELECT id, status, total FROM orders WHERE user_id = 5;
```

This matters most for tables with large or heavy columns -- long text fields, JSON blobs, binary data -- where the unused columns aren't just extra bytes, they can dominate the row size.

## Django

```python
# Before: full model instances, every field
orders = Order.objects.filter(user=u)

# After: only the fields the view/template/serializer actually uses
orders = Order.objects.filter(user=u).only('id', 'status', 'total')
# or, for plain dicts instead of model instances:
orders = Order.objects.filter(user=u).values('id', 'status', 'total')
```

## Where this matters most

- High-traffic list/index endpoints (feeds, dashboards, paginated tables) where the same over-fetch repeats on every request rather than happening once.
- Any endpoint returning many rows -- the wasted cost is per-row, so it compounds with pagination size and result count.
- Tables with large text/JSON/binary columns, where the unused data is disproportionately expensive to move.

## Don't over-apply this

The goal is not fetching what the response doesn't use -- not minimizing column count at any cost. Selecting so narrowly that the code then needs a second query to fetch a field it turns out to need (e.g. for a conditional branch, a permission check, or a related computation) trades one inefficiency for a worse one -- an extra round trip. When in doubt about whether a field will be needed, check how the result is actually used before narrowing the select.
