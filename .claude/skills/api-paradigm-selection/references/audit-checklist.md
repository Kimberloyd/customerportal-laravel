# API Paradigm Audit Checklist

Walk the API/service code under review against these three failure patterns. A codebase can have more than one at once (a REST endpoint being over-fetched by multiple screens AND high-frequency internal calls still on JSON) -- check for all of them, don't stop at the first hit.

## 1. REST over-fetching / under-fetching (a caller-facing REST endpoint that should be reconsidered)

- [ ] Does a client (front-end component, mobile screen) request a REST resource and then only use a small subset of the returned fields, discarding the rest? Look for a fetch/response type with many fields where the rendering code only destructures 2-3 of them.
- [ ] Do multiple different callers (different screens/components) hit the *same* REST endpoint but each only use a different subset of its fields? That's a signal the underlying data has multiple legitimate shapes being forced through one fixed shape.
- [ ] Does assembling one screen/view require calling two or more REST endpoints in sequence (or in parallel but still as separate round-trips) and combining the results client-side? Count the round-trips required for a single view.
- [ ] If either of the above is present and repeats across multiple screens/features (not a one-off), that's a real signal worth raising as "this data-access pattern would benefit from a query-oriented API (GraphQL) or a purpose-built endpoint," not just a one-off inefficiency to patch locally.

## 2. Internal service calls still on JSON/REST at meaningful volume

- [ ] Are two services that are both owned by your own team/org talking to each other over plain HTTP+JSON?
- [ ] Is there any indication of call frequency (metrics, logs, comments, load-testing numbers) suggesting this is a high-volume internal path (many calls per second) rather than an occasional/administrative call?
- [ ] Is there a latency budget mentioned or implied by the calling code (a synchronous call in a user-facing request path) that JSON's parsing/serialization overhead could meaningfully affect?
- [ ] If the call is genuinely low-frequency or non-latency-critical, JSON/REST between internal services is not automatically wrong -- flag gRPC as a potential upgrade only where volume/latency actually justify the added complexity (schema compilation, generated stubs), not as a blanket "internal calls should always be gRPC" rule.

## 3. Paradigm used outside its fit

- [ ] Is GraphQL being used for what's actually a single, fixed-shape public-facing resource with one real caller shape? (Added complexity with no corresponding flexibility being used is worth flagging, though this is a much lower-severity finding than the two above.)
- [ ] Is gRPC being exposed directly to a browser or external/public client (rather than through a REST/GraphQL gateway)? That's a real interoperability problem, not just a style choice -- external callers can't easily consume it.
- [ ] Is a REST public API silently also being used as the transport for high-frequency internal service-to-service calls (i.e., internal services calling through the same public-facing REST layer instead of talking to each other directly)? That couples internal performance to public-API constraints unnecessarily.

## Reporting format

For each finding, state: the endpoint/service/component involved, the concrete evidence (e.g. "`ProfileCard.jsx` fetches the full `/users/:id` REST response but only reads `.name` and `.picture`, while `OrdersScreen.jsx` fetches the same endpoint and only reads `.orders`"), which pattern it matches, and the concrete recommendation (introduce a GraphQL layer for the app's screens, switch this specific internal path to gRPC, etc.) with enough reasoning that the recommendation could be defended, not just asserted. Where the fix is substantial (introducing a new paradigm), propose it as a recommendation with rationale rather than silently rewriting the whole API surface.
