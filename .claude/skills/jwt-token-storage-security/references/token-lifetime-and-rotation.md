# Token Lifetime and Refresh Rotation

## Why lifetime matters even with secure storage

Cookie storage (Step 1) closes the "any script can read it" gap, but tokens can still leak through other channels: a misconfigured logging statement that captures headers, a debugging proxy, a compromised backend service, a browser extension with elevated permissions, a lost device with an unlocked session. Short expiration limits how much damage any single leaked token can do, regardless of how it leaked.

## Recommended lifetimes

- **Access token: 5-15 minutes.** Short enough that a leaked token is only useful briefly, long enough to avoid refreshing on every single request.
- **Refresh token: days to weeks**, scoped tightly (see cookie path scoping in `cookie-based-auth.md` -- send it only to the refresh endpoint, not on every API call, reducing its exposure).

Avoid the common anti-pattern of a single long-lived JWT (hours or days) used directly for every request with no refresh flow at all -- that's the "30-day stolen token" problem this skill exists to fix.

## The rotation flow

1. Access token expires (or the client proactively refreshes shortly before expiry).
2. Client calls the refresh endpoint; the refresh token cookie is sent automatically (scoped to that endpoint's path).
3. Server validates the refresh token, and if valid:
   - Issues a **new** access token.
   - Issues a **new** refresh token, and invalidates the one just used (mark it consumed in storage, or -- if using a signed-token approach without server-side storage -- roll a version/family identifier that the next validation checks against).
4. Client stores neither manually -- both arrive as `Set-Cookie` headers, same as login.

```js
// Node/Express example
app.post('/auth/refresh', async (req, res) => {
  const oldRefreshToken = req.cookies.refresh_token;
  const record = await refreshTokenStore.find(oldRefreshToken);

  if (!record || record.revoked) {
    // Reuse of an already-rotated (or otherwise invalid) refresh token --
    // treat as a signal of possible theft. See "Detecting reuse" below.
    if (record?.revoked) await revokeTokenFamily(record.familyId);
    return res.status(401).json({ error: 'Session invalid, please log in again' });
  }

  await refreshTokenStore.markRevoked(oldRefreshToken);
  const newRefreshToken = await refreshTokenStore.issue(record.userId, record.familyId);
  const newAccessToken = signAccessToken(record.userId);

  res.cookie('access_token', newAccessToken, { httpOnly: true, secure: true, sameSite: 'lax', maxAge: 15 * 60 * 1000 });
  res.cookie('refresh_token', newRefreshToken, { httpOnly: true, secure: true, sameSite: 'lax', path: '/auth/refresh', maxAge: 30 * 24 * 60 * 60 * 1000 });
  res.json({ ok: true });
});
```

## Detecting and responding to refresh-token reuse

Because each refresh token is single-use, a legitimate client will never present an already-rotated one. If one shows up again, it means either a client bug (retried a request after already consuming the token) or that a stolen copy is being replayed by an attacker after the legitimate user already rotated past it. Treat any reuse as a theft signal:

- Group refresh tokens issued from the same original login into a "family" (a shared `familyId`).
- On detecting reuse of a revoked token, revoke the **entire family** -- every refresh token descended from that login -- not just reject the one bad request. This forces re-authentication and cuts off whatever session the attacker was riding, along with the legitimate user's (who will simply need to log in again -- an acceptable cost for a suspected-compromise signal).
- Optionally alert/log this event distinctly from a normal expired-token 401, since it's a stronger security signal worth monitoring.

## What "direct your AI to implement this" means concretely

If delegating this to an AI coding assistant (as the pattern this skill is based on suggests), the instruction needs to specify, not just say "add refresh tokens": the exact access/refresh lifetimes, that rotation must invalidate the previous refresh token on each use, that reused/revoked refresh tokens must revoke the whole token family, and where refresh token state is stored (DB table, Redis, etc.) -- a vague "implement refresh tokens" instruction commonly produces a refresh endpoint that reissues the *same* refresh token indefinitely, which silently defeats the rotation's security purpose while looking correct.
