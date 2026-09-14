---
name: api-paradigm-selection
description: "Use when designing or reviewing an API/service boundary and it's unclear whether REST, GraphQL, or gRPC fits -- a client over-fetching huge REST payloads or making multiple round-trips per screen, or high-frequency internal service calls still on JSON/REST instead of a faster typed protocol. Applies a 3-way framework: REST is resource-oriented (fixed endpoints, simple/mature, best for public APIs, but prone to over-fetching and N+1 round-trips), GraphQL is query-oriented (client picks exactly which fields it wants, best when screens/clients need different data shapes), and gRPC is service-oriented (binary protobuf, typed contracts, built for low-latency internal service traffic and streaming). They don't replace each other -- most real systems use more than one. Use when choosing an API style for a new endpoint/service, or auditing one that doesn't fit its traffic pattern, even without REST/GraphQL/gRPC named directly."
---

# API Paradigm Selection

REST, GraphQL, and gRPC all move data between a client and a server, which makes them look interchangeable -- pick whichever one you already know, wire it up, move on. But each one was built to solve a specific mismatch between what a caller needs and what a naive API gives it, and reaching for the wrong one doesn't just cost elegance, it produces a specific, recognizable symptom: a mobile client drowning in fields it never uses, a screen that needs four round-trips to render, or a payment-critical internal call paying JSON's parsing tax thousands of times a second.

The through-line: don't pick a paradigm out of habit. Look at who's calling (a public client you don't control, or your own frontend, or your own other services), what shape their data needs take (fixed and generic, or varying per screen, or a tightly-coupled typed contract), and how often/how fast the calls need to happen. That combination points to one paradigm far more clearly than familiarity or trend does.

## REST -- resource-oriented

REST models the API as a set of resources, each with its own predefined endpoint (`GET /users/42`, `GET /users/42/orders`, `POST /orders`). A client asks for a resource by URL and HTTP verb; the server returns that resource in a fixed, server-defined shape -- usually JSON. It's the API equivalent of ordering off a menu: you ask for the burger, you get the burger, exactly as the menu defines it, every time.

**Strengths:** simple (a URL and an HTTP verb, nothing to install or learn beyond HTTP itself), mature (every language and client understands it natively), and because of both of those, widely interoperable -- any team, script, or third-party integration can call a REST endpoint with nothing but a URL and `curl`. This combination makes REST the default right choice for a **public API**: the caller is outside your control, so you want the lowest-common-denominator integration story, not a bespoke client library.

**Where it breaks down**, both stemming from the fact that each endpoint's response shape is fixed by the server, not the caller:

- **Over-fetching**: a client that only needs a user's `name` and `picture` still gets back every field the `/users/:id` endpoint is defined to return (email, address, phone, preferences...) because the response shape isn't negotiable per-request. More fields returned than needed means wasted bandwidth and parsing, and it gets worse as the resource grows more fields over the product's lifetime.
- **Under-fetching / N+1 round-trips**: a screen that needs a user's profile AND their recent orders can't get both from one resource endpoint -- it has to call `/users/42` and then `/users/42/orders` separately (and often more calls beyond that), each one a full network round-trip. On a slow or high-latency connection (mobile networks especially), stacking round-trips to assemble one screen is a real, felt performance cost.

## GraphQL -- query-oriented

GraphQL exposes a single endpoint and a schema, and lets the client describe exactly which fields it wants in a query -- `query { user(id: 42) { name picture orders { total } } }`. The server resolves that query and returns precisely those fields, nothing more. It's ordering a custom pizza instead of picking off a fixed menu: you're not constrained to what's on the menu as a bundle, you choose your own toppings, and the shop assembles exactly that.

This directly fixes both of REST's failure modes: **over-fetching** disappears because the client only ever receives the fields it asked for (3 fields asked, 3 returned, not 7), and **under-fetching's round-trip cost** disappears because a single query can traverse relationships (`user` -> `orders`) that would've taken multiple REST calls to assemble, all resolved server-side in one request/response.

**Where it fits best:** any situation where different callers legitimately need different shapes of the same underlying data -- which is the normal situation for a product with multiple front-ends. A profile screen needs `name` + `picture`; an orders screen needs `orders`, `total`, `status`; a checkout screen needs `cart`, `address`, `payment`, `total`. Under REST, that's either three different bespoke endpoints or one bloated endpoint everyone over-fetches from. Under GraphQL, it's the same schema, three different queries. This is why GraphQL is especially associated with front-end and mobile clients -- those are exactly the callers whose data needs vary the most screen-to-screen, and whose network conditions make wasted round-trips and wasted bytes most expensive.

**Trade-offs to weigh, not just benefits:** a GraphQL API is more work to stand up and secure than a REST endpoint (schema design, resolver performance including N+1 problems inside the server now, query cost/complexity limiting so a client can't request something disproportionately expensive), and it's a worse fit for a public API aimed at arbitrary third-party callers who'd rather just `curl` a known URL than write a schema-aware query.

## gRPC -- service-oriented

gRPC is built for a different caller entirely: not a human-facing client, but another one of your own services. As an application grows past a single deployable into many microservices (users, orders, payments, products, cart, search, shipping, reviews, auth...), those services end up calling each other constantly -- easily thousands of times a second in a busy system -- and at that call volume, the overhead JSON/REST carries per call (human-readable text, field names repeated in every payload, parsing overhead) becomes a real cost multiplied across every single one of those calls.

gRPC addresses this with **Protocol Buffers** instead of JSON: a compact binary format with no field names on the wire (fields are numbered, not spelled out), meant to be read by a machine, not a person -- the same payload that's ~28 characters as JSON text can be ~9 bytes as a serialized protobuf message. That directly reduces both serialization cost (turning objects into bytes) and network overhead (bytes actually pushed across the wire), which matters precisely because it's paid on every one of those thousands of per-second internal calls, not because JSON is bad in general -- for a low-frequency public API, the difference is noise.

The other half of gRPC's design is the **typed contract**: a `.proto` file defines every service, method, and message field up front, and both sides of a call agree to that contract before the first call happens. This buys strong typing and compile-time safety across a service boundary that REST/GraphQL, being schema-optional or schema-flexible by comparison, don't enforce as strictly -- valuable specifically because internal services evolve together and a contract mismatch between them should fail loudly at build/deploy time, not silently at runtime.

**Where it fits best:** service-to-service communication inside your own infrastructure -- the users service calling the orders service, the orders service calling the payment service to check "was this payment successful" and needing a fast, typed, low-latency answer. gRPC also natively supports streaming (many messages over one open call), useful for the kind of continuous internal traffic microservices generate. It's a poor fit for a public API or a browser client (it's not natively browser-friendly and callers need the generated client stubs from your `.proto` files, which defeats the "any `curl` can call it" property that makes REST good for that job).

## They don't replace each other

The three paradigms solve different mismatches for different callers, and most real systems that reach meaningful scale end up using more than one at the same time, layered by who's calling:

- **Public API surface** (arbitrary external callers -- web, mobile, partners, scripts): REST, because interoperability and simplicity matter most and you don't control the caller.
- **App data layer** (your own front-end/mobile clients, where different screens need different data shapes): GraphQL, because it eliminates over-fetching and round-trip stacking for the exact caller category that feels those costs most.
- **Service-to-service** (your own backend talking to itself, at high frequency, where you control both ends): gRPC, because performance and a typed contract matter more than human-readability or broad interoperability, and the caller identity/trust means a public-friendly protocol isn't needed.

A single product commonly runs all three: a REST or GraphQL surface exposed to the outside world, with gRPC exclusively for internal microservice traffic that's never reachable from outside at all. Don't treat "we already have a REST API" as a reason every new internal service call or every new client screen has to use REST too -- the right question is always which caller, what shape of data, and what call frequency, not which paradigm the rest of the codebase happens to use.

## Applying this skill

**When designing a new API or service boundary:** identify who the caller actually is (an external/public caller, your own product's front-end, or your own other services) before picking a paradigm. See `references/decision-guide.md` for a structured set of questions and concrete signals for each paradigm, plus example schema/endpoint patterns for each.

**When auditing an existing API architecture:** look for paradigm/caller mismatches -- a REST endpoint being over-fetched by multiple different front-end screens (a GraphQL-shaped problem), a frontend stacking several REST round-trips to assemble one view, or high-frequency internal service calls still riding on JSON/REST when their latency or throughput profile would benefit from gRPC. See `references/audit-checklist.md` for the per-paradigm checklist and expected reporting format.
