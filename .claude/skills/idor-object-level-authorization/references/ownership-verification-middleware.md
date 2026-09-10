# Ownership Verification: Implementation Patterns

## The core shape, regardless of stack

1. Authenticate the request as usual (verify the session/JWT, attach the user to `req.user`/`request.user`/equivalent).
2. Look up the requested resource by its ID.
3. Compare the resource's actual owner field against the authenticated user's ID.
4. If they don't match (and the user doesn't hold an explicit elevated role that's meant to bypass this, like an admin), reject with 403/404 -- not 401, since the user *is* authenticated, they're just not authorized for this specific resource. (Returning 404 instead of 403 is a defensible choice to avoid confirming a resource exists at all to someone who shouldn't see it -- pick based on whether resource existence itself is sensitive.)

## Node/Express example -- reusable ownership middleware

```js
// A factory that produces ownership-check middleware for a given resource type,
// rather than writing the same lookup-and-compare logic in every route handler.
function requireOwnership(model, idParam = 'id', ownerField = 'userId') {
  return async (req, res, next) => {
    const resource = await model.findById(req.params[idParam]);
    if (!resource) {
      return res.status(404).json({ error: 'Not found' });
    }
    if (resource[ownerField] !== req.user.id) {
      // 404 rather than 403 avoids confirming the resource exists to a
      // non-owner -- swap to 403 if resource existence isn't sensitive.
      return res.status(404).json({ error: 'Not found' });
    }
    req.resource = resource; // handlers can reuse the already-fetched resource
    next();
  };
}

// Usage: every order route goes through the same check, not a bespoke inline one.
router.get('/orders/:id', requireAuth, requireOwnership(Order), (req, res) => {
  res.json(req.resource);
});
router.put('/orders/:id', requireAuth, requireOwnership(Order), async (req, res) => {
  const updated = await Order.update(req.resource.id, req.body);
  res.json(updated);
});
router.delete('/orders/:id', requireAuth, requireOwnership(Order), async (req, res) => {
  await Order.delete(req.resource.id);
  res.status(204).end();
});
```

## Django example

```python
# A mixin/decorator applied consistently rather than checked ad hoc per view.
from django.core.exceptions import PermissionDenied
from django.http import Http404

def owned_object_or_404(model, owner_field="user"):
    def decorator(view_func):
        def wrapped(request, *args, **kwargs):
            obj = get_object_or_404(model, pk=kwargs["pk"])
            if getattr(obj, owner_field + "_id") != request.user.id:
                raise Http404()  # avoids confirming existence to a non-owner
            request.resource = obj
            return view_func(request, *args, **kwargs)
        return wrapped
    return decorator

@login_required
@owned_object_or_404(Order)
def order_detail(request, pk):
    return JsonResponse(model_to_dict(request.resource))
```

Or, idiomatically with Django REST Framework, a custom `permissions.BasePermission` applied via `permission_classes` on the ViewSet so every action (retrieve/update/destroy) goes through the same check without per-view duplication.

## Laravel example

Laravel's own Policy/Gate system exists specifically for this and should be used rather than inline checks:

```php
// app/Policies/OrderPolicy.php
class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->user_id;
    }
    public function update(User $user, Order $order): bool
    {
        return $user->id === $order->user_id;
    }
    public function delete(User $user, Order $order): bool
    {
        return $user->id === $order->user_id;
    }
}

// In the controller:
public function show(Order $order)
{
    $this->authorize('view', $order); // throws 403 automatically if it fails
    return response()->json($order);
}
```

Route-model binding fetches the `Order` automatically from the URL's `{order}` parameter, and `authorize()` runs the policy check -- the pattern that keeps every controller action consistent instead of hand-rolling the same `if ($order->user_id !== auth()->id())` in each method.

## Indirect / nested ownership

Not every resource has its own direct owner field -- a comment belongs to a post, which belongs to a user; a line item belongs to an order, which belongs to a user. Verify ownership through the actual chain, not by assuming a shortcut field is trustworthy:

```js
// Don't do this: trusting a client-supplied userId on the nested resource
// without checking it against the actual chain of ownership.
router.delete('/posts/:postId/comments/:commentId', requireAuth, async (req, res) => {
  const comment = await Comment.findById(req.params.commentId);
  // BUG: only checks comment.userId, ignoring whether it's even attached
  // to the postId in the URL, or independently verifying the chain.
  if (comment.userId !== req.user.id) return res.status(404).end();
  await comment.delete();
});

// Do this: verify the resource actually belongs to the parent in the URL,
// and that the parent (or the resource itself) belongs to the user.
router.delete('/posts/:postId/comments/:commentId', requireAuth, async (req, res) => {
  const comment = await Comment.findById(req.params.commentId);
  if (!comment || comment.postId !== req.params.postId) {
    return res.status(404).json({ error: 'Not found' });
  }
  if (comment.userId !== req.user.id) {
    return res.status(404).json({ error: 'Not found' });
  }
  await comment.delete();
});
```

## What this middleware must not do

Never accept an owner/user ID from the request itself (a body field, a query param) as the basis for the ownership check -- always derive "who is asking" from the verified session/token (`req.user.id`), and "who owns this resource" from a fresh database lookup of the resource. If either side of that comparison comes from unverified client input, the check is decorative.
