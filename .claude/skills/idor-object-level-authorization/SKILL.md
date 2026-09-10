---
name: idor-object-level-authorization
description: "Implements and audits protection against IDOR/Broken Object Level Authorization (OWASP API Security #1) using a 3-part pattern: (1) ownership-verification middleware/checks on every API request, comparing the authenticated user against the resource's actual owner instead of only checking that some user is logged in; (2) non-sequential (UUID-style) resource identifiers instead of sequential integers, so IDs can't be enumerated by looping; (3) a systematic audit of every endpoint taking an ID as a URL param or request body field, confirming each enforces ownership. Use when building or reviewing API endpoints that take a resource ID -- especially AI-generated CRUD/REST endpoints, which commonly check authentication but not ownership. Trigger when adding GET/PUT/DELETE /resource/:id endpoints, reviewing API code for security issues, or asked about IDOR, BOLA, changing an ID in a URL to see someone else's data, sequential/predictable IDs, or a user accessing another user's data by editing a request."
---

# IDOR / Broken Object Level Authorization (BOLA)

## The problem this fixes

This is the #1 item on the OWASP API Security Top 10, and one of the most common gaps in AI-generated backends: an endpoint checks that the request is **authenticated** (there's a valid session or token) but never checks that the authenticated user actually **owns** the specific resource being requested. The API knows who's asking -- it just never asks "does this person actually own the thing they're asking for?"

Concretely: `GET /orders/15` returns order 15's data to whoever is logged in, as long as they're logged in at all -- it never compares "who owns order 15" against "who is making this request." Change the `15` to `12` in the URL (or a request body field) and you get someone else's order. This is the exact failure mode this skill targets, and it needs three complementary fixes -- implementing only one leaves a real gap.

## Step 1: Verify resource ownership on every request, not just authentication

Authentication (is this a valid, logged-in user?) and authorization (does *this* user have the right to *this* resource?) are different checks, and an endpoint needs both. The fix is consistent, not ad hoc: every route that reads, modifies, or deletes a specific resource by ID must compare the resource's actual owner against the authenticated user before acting.

- Don't scatter ownership checks as one-off `if` statements copy-pasted per route -- implement it once as reusable middleware/a helper that every resource-scoped route uses, so a new endpoint can't be added without going through the same check.
- The check needs a database lookup of the resource's actual owner -- never infer ownership from anything the client sent (a `userId` field in the request body, a query param) without verifying it server-side against the record itself.
- Apply this to every verb, not just `GET` -- `PUT`/`PATCH`/`DELETE` on someone else's resource is at least as damaging as reading it, and is easy to miss if only read endpoints get audited.
- For resources with indirect ownership (a comment on a post, a line item in someone else's order), verify ownership through the actual chain of custody, not just the immediate record's own `userId` field if it has one -- see `references/ownership-verification-middleware.md` for concrete patterns including nested/indirect ownership.

## Step 2: Stop using sequential, guessable resource IDs

Sequential integer IDs (`user/1`, `user/2`, `order/1000`, `order/1001`) make the resource space trivially enumerable -- an attacker doesn't need to know or guess anything, they just write a loop and walk through every ID in sequence. Combined with a Step 1 gap, this turns one missed ownership check into "every user's data extracted in minutes"; even *with* Step 1 in place, predictable IDs still leak information (total user count, growth rate, existence of a specific record) and make targeted attacks easier to aim.

- Use UUIDs (v4, or a non-guessable ULID/KSUID variant if ordering-by-creation-time is also needed) for any identifier that appears in a URL or is otherwise client-facing, rather than an auto-incrementing integer primary key.
- It's fine to keep an internal auto-incrementing integer primary key for database performance/joins and expose a separate UUID/public-id column as the external identifier -- the fix is about what's exposed to clients and used in routes, not necessarily the internal schema.
- This is a complement to Step 1, not a substitute for it -- an unguessable ID with no ownership check is still exploitable by anyone who obtains one ID legitimately (e.g., their own resource) and is later given or leaks a second one, or via any endpoint that lists/returns IDs it shouldn't.

See `references/non-sequential-identifiers.md` for migration patterns (adding a UUID column to an existing sequential-ID table without a breaking schema change) across common stacks.

## Step 3: Systematically audit every ID-accepting endpoint

An AI-generated backend commonly has dozens of endpoints, built up over many requests/sessions, with inconsistent attention to authorization on each one. Don't assume the fix is applied everywhere just because it's applied somewhere -- enumerate and check every one:

1. List every route that accepts a resource identifier, whether as a URL path parameter (`/orders/:id`), a query parameter (`?orderId=...`), or a request body field (`{ "orderId": "..." }`).
2. For each one, confirm it performs the Step 1 ownership check before reading/modifying/deleting the resource -- not just an authentication check.
3. Pay particular attention to endpoints added later or by a different pass than the original CRUD scaffold (a bulk-action endpoint, a "duplicate this resource" action, a webhook handler, an admin route that got reused for a regular user flow) -- these are the ones most likely to have been added without the same authorization discipline as the original set.

See `references/audit-checklist.md` for a concrete audit procedure and how to test for the gap (not just read code, but issue a request as one authenticated user against another user's resource ID and confirm it's rejected).

## The core principle

Your API should never trust a resource ID supplied by the client as implicit license to act on that resource. It should trust the authenticated user's identity (from the verified session/token) and then verify, server-side, that the identity actually owns whatever resource the ID points to -- every time, for every ID-accepting endpoint.
