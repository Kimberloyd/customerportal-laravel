# Non-Sequential Identifiers: Why and How

## Why sequential IDs are a real problem, not just untidy

A sequential integer ID (`/users/1`, `/users/2`, `/orders/1000`, `/orders/1001`) requires zero information to enumerate -- an attacker just increments a number in a loop. This matters at two layers:

- **Combined with a missing ownership check (Step 1), it's catastrophic**: instead of needing to somehow discover other users' IDs, the attacker can walk the entire ID space and harvest every record in minutes.
- **Even with ownership checks correctly in place, sequential IDs still leak information**: total record count (how many users/orders/whatever exist), growth rate over time (create two accounts a day apart, diff the IDs), and they make it trivial for an attacker to confirm *that* a specific record exists even if they can't read its contents (a 403 on `/orders/4501` vs a 404 on `/orders/9999999` tells you 4501 is a real order).

Non-sequential IDs don't replace ownership checks -- they remove the "free enumeration" property and reduce information leakage, as a complementary layer.

## What to use instead

- **UUID v4**: fully random, no embedded information, the simplest default choice for external-facing resource identifiers.
- **ULID or KSUID**: sortable-by-creation-time while still effectively unguessable (they embed a timestamp but the rest is random) -- use these instead of UUID v4 specifically when you need IDs that sort chronologically (e.g., for pagination or a naturally time-ordered listing) without falling back to an exposed sequential integer.

## Migrating an existing table off sequential IDs

A full primary-key migration is invasive and often unnecessary. The common, lower-risk pattern: keep the internal auto-incrementing integer as the database primary key (it's fine for internal joins/performance and is never exposed to clients), and add a separate public-facing UUID column that all external routes and responses use instead.

### Node/Postgres example

```sql
ALTER TABLE orders ADD COLUMN public_id UUID NOT NULL DEFAULT gen_random_uuid();
CREATE UNIQUE INDEX orders_public_id_idx ON orders (public_id);
```

```js
// Routes use public_id, not the internal integer id
router.get('/orders/:publicId', requireAuth, async (req, res) => {
  const order = await Order.findOne({ where: { public_id: req.params.publicId } });
  // ... ownership check as in ownership-verification-middleware.md
});

// Never include the internal integer id in API responses
res.json({
  id: order.public_id,     // expose this
  // internal `id` (integer) is not serialized in the response at all
  total: order.total,
  createdAt: order.createdAt,
});
```

### Django example

```python
import uuid
from django.db import models

class Order(models.Model):
    id = models.AutoField(primary_key=True)  # internal, never exposed
    public_id = models.UUIDField(default=uuid.uuid4, unique=True, editable=False)
    # ...

# urls.py uses public_id
path('orders/<uuid:public_id>/', OrderDetailView.as_view())
```

### Laravel example

Laravel supports UUID route-model binding directly:

```php
// Model
class Order extends Model
{
    protected $primaryKey = 'id'; // internal integer, unaffected
    public function getRouteKeyName() { return 'public_id'; }
}

// Migration
$table->uuid('public_id')->unique();

// Routes automatically resolve by public_id instead of the integer id
Route::get('/orders/{order}', [OrderController::class, 'show']);
```

## Checklist for the migration

- [ ] Add the new identifier column with a default generator (so existing rows get backfilled) and a unique index.
- [ ] Update every route, redirect, and generated link to use the new identifier instead of the internal integer.
- [ ] Update any API response serialization to stop including the internal integer ID at all -- if it's still present in a response payload even unused by the frontend, it's still exposed to enumeration.
- [ ] Update any frontend code, mobile app, or external integration that constructs URLs from the old ID -- this is usually the actual migration effort, not the database change itself.
- [ ] Leave the internal integer primary key in place for database performance (joins, indexing) -- there's no need to migrate the primary key itself, only what's exposed externally.
