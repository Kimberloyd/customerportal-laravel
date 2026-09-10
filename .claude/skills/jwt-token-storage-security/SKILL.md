---
name: jwt-token-storage-security
description: "Implements and audits secure JWT/session token handling using a 3-part pattern: (1) storing the auth token in an HttpOnly, SameSite cookie instead of localStorage/sessionStorage, so page JavaScript (incl. a compromised package or third-party widget) can never read it; (2) short-lived access tokens with refresh-token rotation, so a stolen token has a narrow window and reuse of an old refresh token is detectable; (3) real token revocation (a denylist or a per-user token version) so a password change or logout actually invalidates outstanding sessions. Use whenever building or reviewing auth/login/session code -- especially AI-generated auth scaffolding, which commonly stores JWTs in localStorage by default. Trigger when adding login/auth, when a token is stored in localStorage/sessionStorage, when reviewing auth code for security issues, when asked about XSS token theft, refresh tokens, or logout/password-change not invalidating sessions, or when a token never expires or can't be revoked."
---

# JWT / Session Token Storage Security

## The problem this fixes

A very common pattern in AI-generated (and plenty of hand-written) login systems: the user authenticates, the server issues a JWT, and the frontend stores it in `localStorage` (or `sessionStorage`) so it can attach it to future requests. This feels natural -- it's simple to read from anywhere in the app -- but it means the token is sitting in a location that **any JavaScript running on the page can read**: an XSS payload, a compromised or malicious npm package, a third-party script tag (an analytics snippet, a chat widget, an ad script) all have the same access to `localStorage` as your own application code. That token is the user's full identity; anyone who reads it out of storage can act as that user until it expires -- and if it never expires or can't be revoked, that access can be indefinite.

This skill applies three fixes together, in order. They're complementary, not alternatives -- implementing only one leaves a real gap.

## Step 1: Move the token out of storage the page can read

Store the token in an **HttpOnly, Secure, SameSite cookie**, set by the server at authentication, rather than returning it in the response body for the frontend to store itself.

- `HttpOnly` means client-side JavaScript cannot read the cookie at all (`document.cookie` won't show it) -- this is what closes the XSS-can-read-it-from-storage gap. The cookie still travels automatically with every request to the API domain; the frontend doesn't need to manually attach it.
- `Secure` ensures the cookie is only ever sent over HTTPS.
- `SameSite=Strict` or `SameSite=Lax` (choose based on whether cross-site navigation needs to carry the cookie -- `Lax` is the common default for top-level navigation flows, `Strict` where that's not needed) limits the cookie being sent on cross-site requests, which is the first layer of CSRF mitigation for cookie-based auth.
- Because the browser now sends the cookie automatically, and JavaScript can't read or attach it manually, any state-changing request (POST/PUT/PATCH/DELETE) needs an explicit CSRF defense to compensate -- this is a direct, necessary consequence of moving to cookie auth, not optional hardening. The standard approaches are a double-submit CSRF token (a non-HttpOnly token the frontend reads and sends back in a header, compared server-side to a value tied to the session) or checking the `Origin`/`Referer` header on mutating requests. Don't ship cookie-based auth without one of these -- it's the tradeoff that comes with fixing the localStorage problem.
- If the frontend is a fully separate origin from the API (a common SPA + API split), configure CORS with `credentials: true` and an explicit (non-wildcard) allowed origin, and set the cookie's `Domain`/`SameSite` to actually work across that origin split -- verify this in practice, don't assume default cookie behavior works cross-origin.

See `references/cookie-based-auth.md` for concrete server-side (setting the cookie) and client-side (removing manual token handling) implementation patterns across common stacks.

## Step 2: Short-lived access tokens + refresh token rotation

Even with the token out of client-readable storage, it can still leak (a server-side log, a proxy, a network capture, a browser extension with elevated access). Limit the blast radius:

- **Access tokens should be short-lived** -- commonly 5-15 minutes, not hours or days. A stolen token that's only valid for 30 days does far more damage than one valid for 10 minutes.
- **Use a separate, longer-lived refresh token** (also stored HttpOnly, not exposed to JS) to silently obtain new access tokens without forcing re-login every few minutes.
- **Rotate the refresh token on every use**: each time it's exchanged for a new access token, issue a *new* refresh token and invalidate the old one. This means a refresh token can only be used once -- if a stolen refresh token is ever replayed after the legitimate one has already rotated, that's a detectable signal of theft (the "old" token being reused), and the correct response is to revoke the entire token family, not just reject the one request.
- Don't reissue the same refresh token indefinitely on each use -- that reintroduces a long-lived credential through the back door and defeats the point of rotation.

See `references/token-lifetime-and-rotation.md` for the rotation flow in detail, including how to detect and respond to refresh-token reuse.

## Step 3: Real token revocation

A JWT's core property -- it's self-contained and verifiable without a database lookup -- is also its weakness: by default, nothing can invalidate one before it expires. Without a revocation mechanism, a password change, an explicit logout, or an admin-initiated "sign this user out everywhere" action does nothing to tokens already issued; they stay valid until they naturally expire. Implement one of:

- **A revocation/denylist**: store revoked token IDs (`jti` claim) or refresh-token IDs in a fast-lookup store (Redis, a DB table with an index) with a TTL matching the token's remaining lifetime, and check it on every request that verifies the token. This adds a lookup per request but gives precise, immediate revocation of individual tokens.
- **A per-user token version/generation number**: store a version number on the user record, embed it as a claim in every token issued, and increment it on password change, logout-everywhere, or a suspected compromise. Verification checks the token's version claim against the user's current version -- if they don't match, the token is rejected. This needs one lookup of the user's current version (often already happening as part of loading the user) rather than a separate revocation-store lookup per token, and revokes *all* outstanding tokens for that user at once rather than one at a time.

Pick the revocation/denylist approach when you need to revoke individual sessions (log out this one device, not all of them) or handle high token volume where a version bump is too coarse; pick the per-user version approach when the trigger is inherently "kill everything for this user" (password change, account compromise) and you want it cheap and simple. Many real systems use both: version bump for the coarse "password changed" case, and a denylist for "log out this specific device."

At minimum, revoke on: password change, explicit logout, and any admin/security action that should end a session immediately.

See `references/revocation-strategies.md` for implementation patterns for both approaches.

## Auditing existing auth code

When reviewing rather than building, check for all three gaps concretely: search for where the token is stored client-side (`localStorage.setItem`, `sessionStorage.setItem` with anything resembling a token/JWT), check the access token's expiration claim and whether a refresh flow with rotation exists at all, and check whether logout/password-change actually invalidates server-side state or merely clears client-side storage (a very common half-fix -- clearing `localStorage` on logout does nothing if the token itself is still valid and an attacker already has a copy). Use `references/audit-checklist.md` and point at the specific file/line where the token is stored, the specific expiration value found, and whether logout has any server-side effect at all -- not a general "auth could be more secure" impression.
