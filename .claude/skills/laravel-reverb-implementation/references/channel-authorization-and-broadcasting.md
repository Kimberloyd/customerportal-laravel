# Channel authorization and broadcast delivery

These are two different failure modes and it's worth checking both even if the user only flagged one — an app can have airtight channel authorization and still broadcast nothing because no queue worker is running, or have a queue worker humming along fine while leaking presence-channel data to anyone who can guess a room name.

## Authorization: channel design is the permission model

Every private or presence channel subscription is authorized through a single checkpoint — the callback registered in `routes/channels.php`. There's no secondary check later; if the callback returns something truthy, the client is in.

```php
use App\Models\User;

Broadcast::channel('orders.{orderId}', function (User $user, int $orderId) {
    return $user->id === Order::findOrNew($orderId)->user_id;
});
```

For presence channels, return an array of the data other members should see instead of a boolean — and treat that return value as public to everyone else on the channel:

```php
Broadcast::channel('chat.{roomId}', function (User $user, int $roomId) {
    if ($user->canJoinRoom($roomId)) {
        return ['id' => $user->id, 'name' => $user->name]; // exactly what every other member receives — nothing more
    }
    // returning false/null denies the subscription
});
```

Two things worth auditing specifically, because both are easy to get subtly wrong without the channel visibly "not working":

- **The channel name itself should embed and validate the owning identifier**, not just accept any ID and check it inside the closure against the wrong scope. In a multi-tenant app, `orders.{orderId}` alone (checking only `user_id`) is weaker than `tenants.{tenantId}.orders.{orderId}` validating both the tenant and the user — the first design means a bug or a future refactor only has to get one check wrong to leak across tenants; the second makes the mistake harder to make.
- **Presence channel payloads should carry exactly what other members need to see and nothing else.** It's easy to return the full user model (or most of it) out of convenience — that puts email addresses, roles, or internal flags in front of every other subscriber on the channel, whether or not the UI happens to display them.

## Delivery: broadcasting is queued by default

`ShouldBroadcast` events go through the queue, not out immediately — this is deliberate (so broadcasting a large payload doesn't add latency to the request that triggered it), but it means **a Reverb server with a correct setup and zero queue workers will silently accumulate undelivered broadcast jobs while every other symptom looks fine**: the event fires, no exception is thrown, Reverb is up, the frontend is subscribed correctly — nothing happens, because nothing ever pulled the job off the queue.

```bash
php artisan queue:work
```

If a broadcast genuinely needs to skip the queue (rare — usually only for something already latency-sensitive enough that even normal queue delay matters), implement `ShouldBroadcastNow` instead of `ShouldBroadcast` on the event class; that sends synchronously in the same request instead of going through a worker.

**Isolate broadcast jobs onto their own queue** rather than sharing the default queue with everything else the app dispatches. A batch of image-processing or report-generation jobs sitting ahead of a broadcast in the same queue turns a "real-time" notification into one that arrives whenever the backlog clears:

```php
class OrderStatusUpdated implements ShouldBroadcast
{
    public $queue = 'broadcasts';
}
```

...and run a worker (or a Supervisor/systemd program, in production — see `references/production-deployment.md`) dedicated to that queue:

```bash
php artisan queue:work --queue=broadcasts
```

## Checking what's actually broken

When a report is "real-time updates aren't arriving," the fault tree is short and worth walking in order rather than guessing: is `reverb:start` actually running (not just configured), is a queue worker actually running and consuming the queue the broadcast event uses, does the channel authorization callback actually return true for this user (test directly — an incorrectly-denied subscription looks identical to "nothing was broadcast" from the frontend), and does the frontend `Echo.channel()`/`Echo.private()`/`Echo.join()` call use the exact same channel name the backend broadcasts on (a mismatched `{orderId}` vs `{order_id}` naming convention between the PHP and JS sides is a common, silent miss).
