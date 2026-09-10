# IDOR / BOLA Audit Checklist

Use this to systematically review an existing API for the exact gap this skill targets -- point at the specific route/file/line found, not a general "authorization could be stronger" impression.

## Step 1: Enumerate every ID-accepting endpoint

Before judging anything, build the actual list -- don't rely on memory or spot-checking a few routes you assume are representative:

- [ ] Search route definitions for path parameters that look like resource identifiers (`:id`, `:orderId`, `{id}`, `<int:pk>`, `<uuid:public_id>`, etc.).
- [ ] Search for endpoints that accept an identifier via query string (`?orderId=`, `?userId=`) rather than the URL path.
- [ ] Search request body schemas/validators for fields that reference another resource by ID (`{ "orderId": "...", ... }` in a POST/PUT body) -- these are just as exploitable as a URL param and are more likely to be missed in a path-focused review.
- [ ] Note every HTTP verb present per resource (`GET`, `PUT`, `PATCH`, `DELETE`) -- a resource often has an ownership check on `GET` but not on `DELETE`, since they're frequently added at different times.

## Step 2: For each endpoint found, check the actual authorization logic

- [ ] Does the handler look up the resource's real owner from the database and compare it against the authenticated user (`req.user.id` or equivalent), or does it only check that *some* user is authenticated (`requireAuth` alone, with no ownership comparison at all)?
- [ ] Is the ownership check derived entirely from server-verified state (the authenticated session + a fresh DB lookup), or does any part of the comparison trust client-supplied data (a `userId` field in the request body, a role/ownership claim taken from an unverified source)?
- [ ] For nested/indirect resources (a comment on a post, a line item on an order), is the full ownership chain verified, or only the leaf resource's own owner field (missing a check that it's actually attached to the parent referenced in the URL)?
- [ ] Is the same check applied consistently across a resource's full route set, or present on some verbs/routes and missing on others (very commonly: present on `GET`, missing on `DELETE` or a bulk-action endpoint added later)?

## Step 3: Check identifier predictability

- [ ] Are resource IDs sequential integers exposed directly in routes/responses, or non-sequential (UUID/ULID)? Note this as a separate, lower-severity finding from a missing ownership check -- both matter, but a missing ownership check on a sequential ID is the most severe combination (trivial mass enumeration).
- [ ] If IDs are already non-sequential, confirm the internal sequential primary key (if one still exists) is never included in any API response or exposed in any route -- a UUID migration that still leaks the old integer ID in a JSON payload doesn't fully close the enumeration gap.

## Step 4: Verify by testing, not just reading

Where possible, don't stop at reading the code -- actually issue a request as one authenticated user against a resource ID known to belong to a different user (a second test account, or a seeded fixture with two distinct owners) and confirm the response is a rejection (403/404), not the resource's data. Code review can miss a check that looks present but has a subtle logic bug (comparing the wrong fields, an `||` that should be `&&`, a check that runs but its result is never used to actually block the response).

## Presenting findings

For each gap, cite the specific route (method + path), the specific line where the ownership check is missing or incorrect, and whether the identifier is sequential or not -- then propose the specific fix (which middleware/policy pattern from `ownership-verification-middleware.md` applies, whether a UUID migration per `non-sequential-identifiers.md` is warranted) rather than a general recommendation to "improve API authorization."
