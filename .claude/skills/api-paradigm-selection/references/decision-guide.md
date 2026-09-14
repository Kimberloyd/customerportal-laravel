# API Paradigm Decision Guide

A structured set of questions to work through when designing a new API/service boundary, plus concrete patterns for each paradigm.

## The three questions

1. **Who is the caller?**
   - An external party you don't control (public web/mobile client, a partner, a third-party script) -> lean REST.
   - Your own product's front-end/mobile client(s) -> lean GraphQL if different screens/clients need different data shapes; plain REST is fine if there's really only one shape everyone needs.
   - Your own other backend services -> lean gRPC, especially at meaningful call volume.

2. **Does the data shape vary by caller?**
   - One fixed shape works for everyone -> REST is simplest and sufficient.
   - Different screens/clients genuinely need different subsets/combinations of the same underlying data -> GraphQL's per-query field selection removes the over-fetch/under-fetch tradeoff REST would otherwise force.

3. **What's the call volume and latency sensitivity?**
   - Low/moderate frequency, human-readable payloads useful for debugging -> REST/JSON overhead is not worth optimizing away.
   - High frequency (many calls/second) and/or latency-critical, especially service-to-service -> gRPC's binary protocol and typed contract pay for themselves; JSON's per-call text overhead becomes a real, measurable cost at that volume.

## REST patterns

```
GET  /users/42                 -> the User resource, full server-defined shape
GET  /users/42/orders          -> the Orders resource for that user
POST /orders                   -> create an Order (an operation, not strictly a resource)
```

Signs REST is a good fit: the caller is external/public, there's one reasonable response shape for the resource, and simplicity/interoperability outweigh the cost of occasional over-fetching.

Signs REST is straining: multiple different front-end screens each need a different subset of the same resource's fields (some calling code silently discards most of the response), or assembling one screen requires calling two or more resource endpoints and stitching results together client-side.

## GraphQL patterns

```graphql
# Schema (server-defined, but client chooses which fields to request)
type User {
  id: ID!
  name: String!
  email: String!
  address: Address
  orders: [Order!]!
}

# Profile screen's query -- only what it needs
query {
  user(id: 42) {
    name
    picture
  }
}

# Orders screen's query -- same schema, different shape
query {
  user(id: 42) {
    orders {
      total
      status
    }
  }
}
```

Signs GraphQL is a good fit: you can point to two or more real callers (screens, clients) of the same underlying data that need visibly different subsets of it, and/or a client currently makes multiple REST calls to assemble one view.

Signs GraphQL is overkill: there's only one caller/shape, or the team doesn't have the resolver-performance and query-complexity-limiting discipline to keep a flexible query surface from becoming its own performance/security problem (an unbounded nested query can be as expensive as a small denial-of-service if the server doesn't guard against it).

## gRPC patterns

```protobuf
// order_service.proto -- typed contract both sides compile against
service OrderService {
  rpc GetOrder (GetOrderRequest) returns (Order);
  rpc StreamOrderUpdates (OrderSubscription) returns (stream OrderUpdate);
}

message Order {
  int64 id = 1;
  bool paid = 2;
  int64 total_cents = 3;
}
```

Signs gRPC is a good fit: both ends of the call are services you control, the call happens at high frequency or under tight latency budgets, and/or you want a compiler-enforced contract between services that evolve together.

Signs gRPC is the wrong tool: the caller is a browser or an external/public client (gRPC isn't natively browser-friendly and requires generated client stubs, which defeats the "anyone can call it with curl" property REST/GraphQL-over-HTTP give you), or call volume is low enough that JSON's overhead was never actually a measured problem -- don't adopt gRPC's added complexity (schema compilation step, generated stubs, less human-debuggable wire format) without a concrete performance/typing reason.
