# JWT/Session Security Audit Checklist

Use this to review existing authentication code for the three specific gaps this skill targets -- point at the exact file/line/value found, not a general "auth needs work" impression.

## Storage location

- [ ] Search for `localStorage.setItem` / `sessionStorage.setItem` (or the framework-equivalent client-side storage APIs) with a value that looks like a token, JWT, or auth credential. Any hit here is the core vulnerability -- any script on the page can read it.
- [ ] If the token is returned in a login/refresh response body at all (rather than only set via `Set-Cookie`), that's a variant of the same problem even if the frontend doesn't persist it -- it was still exposed to page JS at least transiently, and invites a future engineer to start storing it.
- [ ] If cookies are already used, verify the actual flags set: `HttpOnly` (required -- if absent, JS can still read it via `document.cookie`), `Secure` (required in production -- otherwise it can be sent over plain HTTP), and `SameSite` (should be `Lax` or `Strict`, not `None` unless there's a specific, deliberate cross-site requirement backed by a separate CSRF defense).
- [ ] If cookies are used for auth, confirm a CSRF defense exists for state-changing requests (a CSRF token, or Origin/Referer checking) -- cookie auth without this is a new gap introduced by "fixing" the storage problem.

## Token lifetime and rotation

- [ ] Find where the access token is signed and check its expiration (`expiresIn` / `exp` claim). Anything measured in hours or days for an access token (rather than single-digit minutes) leaves a wide window if the token does leak through any channel.
- [ ] Does a refresh flow exist at all? If access tokens are long-lived specifically because there's no refresh mechanism, that's the anti-pattern this skill targets -- the fix is adding rotation, not just accepting the long lifetime.
- [ ] If a refresh flow exists, does it actually rotate the refresh token (invalidate the old one, issue a new one) on each use, or does it silently reissue the same refresh token indefinitely? The latter looks like it works but defeats rotation's security purpose -- a stolen refresh token would remain valid indefinitely, same as the original problem one level down.
- [ ] Is reuse of an already-rotated/revoked refresh token detected and treated as a signal (revoking the token family), or does the endpoint just reject the single bad request and move on?

## Revocation

- [ ] Does logout do anything server-side, or does it only clear the client-side cookie/storage? Test (or trace through the code) whether a token obtained before logout still authenticates successfully against the API after logout -- if yes, there's no real revocation.
- [ ] Does changing a password invalidate previously issued tokens? Trace whether the password-change handler touches any revocation mechanism (denylist entry, version bump) or only updates the password hash.
- [ ] Is there any admin/support-facing way to force-invalidate a specific user's active sessions, and does it actually work end-to-end (not just disable future logins)?
- [ ] If a revocation list is used, confirm entries have a TTL matching the token's remaining lifetime (an unbounded revocation list that never expires entries is a slow memory/storage leak) and that the check happens on every request that verifies a token, not only some routes.

## Presenting findings

For each gap found, cite the concrete evidence (the exact `localStorage.setItem(...)` call and its file/line, the exact `expiresIn` value found, whether logout's handler contains any server-side call at all) and propose the specific fix from Steps 1-3 in SKILL.md rather than a general recommendation to "improve token security."
