# Cookie-Based Auth: Implementation Patterns

## The core shape, regardless of stack

1. On successful login, the server sets the token in a `Set-Cookie` header with `HttpOnly`, `Secure`, and an appropriate `SameSite` value -- it does **not** return the token in the JSON response body for the client to store.
2. The client makes no changes to store or attach the token -- it simply includes credentials on requests (`fetch(..., { credentials: 'include' })` or equivalent), and the browser handles attaching the cookie automatically.
3. On the server, auth middleware reads the token from the incoming cookie rather than an `Authorization: Bearer` header.
4. Logout clears the cookie server-side (`Set-Cookie` with an expired date) -- see `revocation-strategies.md` for why clearing the cookie alone is not sufficient revocation.

## Node/Express example

```js
// Login: set the cookie instead of returning the token in the body
app.post('/login', async (req, res) => {
  const user = await authenticate(req.body.email, req.body.password);
  const accessToken = signAccessToken(user); // short-lived, see token-lifetime-and-rotation.md
  const refreshToken = await issueRefreshToken(user);

  res.cookie('access_token', accessToken, {
    httpOnly: true,
    secure: true,          // HTTPS only
    sameSite: 'lax',       // or 'strict' -- see note on choosing below
    maxAge: 15 * 60 * 1000 // matches access token expiry
  });
  res.cookie('refresh_token', refreshToken, {
    httpOnly: true,
    secure: true,
    sameSite: 'lax',
    path: '/auth/refresh', // scope the refresh cookie to only the refresh endpoint
    maxAge: 30 * 24 * 60 * 60 * 1000
  });

  res.json({ user: publicUserFields(user) }); // no token in the body
});

// Auth middleware: read from cookie, not an Authorization header
function requireAuth(req, res, next) {
  const token = req.cookies.access_token;
  if (!token) return res.status(401).json({ error: 'Not authenticated' });
  try {
    req.user = verifyAccessToken(token); // also checks revocation, see revocation-strategies.md
    next();
  } catch {
    res.status(401).json({ error: 'Invalid or expired session' });
  }
}
```

## Django example

```python
# settings.py
SESSION_COOKIE_HTTPONLY = True
SESSION_COOKIE_SECURE = True
SESSION_COOKIE_SAMESITE = "Lax"
# If issuing your own JWTs rather than using Django's session framework,
# apply the same flags explicitly when calling response.set_cookie(...).

# view
def login_view(request):
    user = authenticate(request, ...)
    access_token = sign_access_token(user)
    response = JsonResponse({"user": public_user_fields(user)})
    response.set_cookie(
        "access_token", access_token,
        httponly=True, secure=True, samesite="Lax",
        max_age=15 * 60,
    )
    return response
```

## Laravel example

Laravel's own session cookies already default to `HttpOnly` and support `SameSite` config (`config/session.php`); for a token-based API, replicate the same flags when issuing the cookie manually:

```php
return response()->json(['user' => $user->only(['id', 'name', 'email'])])
    ->cookie('access_token', $accessToken, 15, '/', null, true, true, false, 'Lax');
    // minutes, path, domain, secure, httpOnly, raw, sameSite
```

## Choosing SameSite=Lax vs Strict

- `Strict`: the cookie is never sent on cross-site requests, including top-level navigation from an external link. Use this when the app has no legitimate cross-site entry flow (e.g., a pure SPA dashboard users navigate to directly).
- `Lax`: the cookie is sent on top-level cross-site navigation (e.g., clicking a link from an email that lands on the app) but not on cross-site subrequests (an `<img>`, a background `fetch` from another origin). This is the more common default and avoids breaking legitimate "click a link, land signed in" flows while still blocking the cross-site POST forgery pattern CSRF exploits.
- Whichever is chosen, pair it with an explicit CSRF defense for state-changing requests (see Step 1's CSRF note in SKILL.md) -- `SameSite` alone is defense in depth, not a complete CSRF solution on its own (older browsers, subdomain-scoped attacks, and some navigation edge cases can still get through).

## Removing the client-side half of the old pattern

When migrating an existing app off localStorage token storage, remove (don't just stop using) the client-side token handling: delete `localStorage.setItem('token', ...)` / `localStorage.getItem('token')` calls, remove any `Authorization: Bearer ${token}` header construction in the HTTP client, and switch fetch/axios calls to send credentials (`credentials: 'include'` for fetch, `withCredentials: true` for axios) so the browser attaches the cookie. Leaving the old localStorage code in place even if unused is a lingering attack surface if it's ever accidentally reactivated or copy-pasted elsewhere.
