# Token Revocation Strategies

## Why this is needed at all

A JWT's defining feature is that it's self-verifying -- the server checks the signature and claims without a database round-trip, which is why JWTs are fast and scale well. But that same property means that, by default, **nothing can invalidate a JWT before it expires**. If a user changes their password because they suspect compromise, or an admin needs to force-logout a user, a JWT issued five minutes ago is still perfectly valid to the server unless something explicit checks for revocation. Clearing the cookie or `localStorage` entry on the client does nothing -- if an attacker already has a copy of the token, clearing the *legitimate* user's client-side copy doesn't touch the attacker's copy.

## Option A: Revocation list / denylist

Maintain a store (Redis is a common choice for its TTL support, but any fast-lookup store works) of revoked token identifiers.

```js
// Include a unique jti (JWT ID) claim when signing
function signAccessToken(user) {
  return jwt.sign(
    { sub: user.id, jti: crypto.randomUUID() },
    ACCESS_TOKEN_SECRET,
    { expiresIn: '15m' }
  );
}

// On revocation (logout, password change, admin action):
async function revokeToken(jti, expiresInSeconds) {
  await redis.set(`revoked:${jti}`, '1', 'EX', expiresInSeconds);
  // TTL matches the token's remaining lifetime -- no need to store the
  // revocation forever once the token would have expired naturally anyway.
}

// On every request verifying the token:
async function verifyAccessToken(token) {
  const payload = jwt.verify(token, ACCESS_TOKEN_SECRET);
  const isRevoked = await redis.exists(`revoked:${payload.jti}`);
  if (isRevoked) throw new Error('Token has been revoked');
  return payload;
}
```

**Tradeoffs:** precise -- can revoke one specific token/session without affecting others (log out this one device). Costs a store lookup on every request that verifies a token, though this is typically fast (sub-millisecond with Redis) and worth it for the precision.

## Option B: Per-user token version / generation number

Store a version number on the user record; embed it as a claim in every token; bump it to invalidate everything issued before the bump.

```js
// user record has a `tokenVersion` column, starting at 0

function signAccessToken(user) {
  return jwt.sign(
    { sub: user.id, tokenVersion: user.tokenVersion },
    ACCESS_TOKEN_SECRET,
    { expiresIn: '15m' }
  );
}

async function verifyAccessToken(token) {
  const payload = jwt.verify(token, ACCESS_TOKEN_SECRET);
  const user = await db.users.findById(payload.sub); // often already needed to load the user anyway
  if (user.tokenVersion !== payload.tokenVersion) {
    throw new Error('Token has been invalidated');
  }
  return payload;
}

// On password change or "log out everywhere":
async function invalidateAllSessions(userId) {
  await db.users.update(userId, { tokenVersion: db.raw('token_version + 1') });
}
```

**Tradeoffs:** cheap and simple -- one column, one lookup that's often already happening as part of loading the authenticated user. But it's all-or-nothing per user: bumping the version invalidates *every* outstanding token for that user, including sessions the user didn't intend to log out (e.g., their other logged-in devices). Good fit for "something happened that means everything should be invalidated" (password change, suspected compromise) rather than "log out just this one device."

## Combining both

A common real-world setup: per-user version bump for the coarse cases (password change, "log out everywhere," admin-forced logout), plus a denylist for precise single-session revocation (logging out one specific device from a device-management UI, or revoking a token family after detecting refresh-token reuse -- see `token-lifetime-and-rotation.md`). Neither alone covers both use cases well.

## What must trigger revocation, at minimum

- **Password change** -- the whole point of changing a password after suspected compromise is defeated if old tokens remain valid.
- **Explicit logout** -- clearing the client-side cookie is necessary but not sufficient; the server must also revoke server-side (denylist the token, or accept that a version-based approach handles this differently -- logout typically doesn't need a version bump since it's user-initiated and only affects their own session/device, but it does need the specific token/session revoked if using the denylist approach).
- **Admin/security action** -- account suspension, detected compromise, or a support-initiated "force logout" should have a real mechanism to invalidate active sessions immediately, not just prevent new logins.
