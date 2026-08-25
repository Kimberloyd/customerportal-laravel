---
name: performance-optimization
description: >
  Review, profile, or improve performance in this Laravel, Inertia, React, and
  Vite application. Use for slow requests, database queries, upstream API
  latency, rendering, large lists, bundle size, memory, caching, queues, or
  deployment/runtime tuning. Measure before changing and preserve behavior,
  authorization boundaries, UI, and public response contracts.
---

# Performance Optimization

Find the actual bottleneck, make the smallest justified improvement when the
user has requested changes, and verify the result. A review request authorizes
inspection and reporting only; do not edit application code unless the user
also asks for fixes or implementation.

## Repository Context

Verify versions from the lockfiles before relying on framework behavior. The
current application is:

- Laravel/PHP backend with controllers and support classes under `app/`.
- Inertia React frontend under `resources/js`; pages receive server props from
  `Inertia::render(...)` rather than a standalone frontend API layer.
- Vite entry point at `resources/js/app.jsx` and Tailwind styles under
  `resources/css`.
- External inventory access through `app/Support/InventoryApiClient.php` using
  Laravel's HTTP client.
- Docker-based local/runtime environment. Production shares business tables
  with another application; honor `DEPLOY.md`, especially its prohibition on
  running Laravel migrations against that shared database.

Do not assume this repository contains FastAPI, websockets, frontend data
hooks, or a JavaScript fetch layer. Search before making architecture claims.

## Workflow

1. Confirm the request mode: review/profile, or implement fixes. Preserve the
   working tree and avoid unrelated edits.
2. Identify the affected path from browser interaction through route,
   middleware, controller/service, database or upstream HTTP calls, Inertia
   props, and React rendering.
3. Capture a reproducible baseline appropriate to the suspected bottleneck.
   Record the command, route/scenario, data volume, environment, and metric.
4. Rank findings by measured user impact, confidence, implementation risk, and
   effort. Do not label a code pattern a bottleneck without evidence.
5. If implementation is requested, apply one logically isolated optimization
   at a time and compare against the same baseline.
6. Re-read the final diff and run verification proportional to the change.

Build success proves compilation only. It does not prove faster requests,
unchanged UI, correct authorization, or equivalent responses.

## Measuring This Application

Choose only the measurements relevant to the request:

- Backend: request wall time, database query count/time, peak memory, upstream
  HTTP duration, and response payload size. Use Laravel/PHP instrumentation,
  targeted tests, query logging, or database `EXPLAIN`; remove temporary
  instrumentation before finishing.
- Frontend: browser Network and Performance panels, React Profiler, Web Vitals,
  and a production Vite build. Distinguish initial-route assets from chunks
  loaded only when a feature is opened.
- Data scale: test with representative row counts. A fast empty database is not
  evidence that a report or table scales.
- Deployment: compare in the same environment and configuration. Development
  timing, Docker Desktop file sharing, and production PHP-FPM/opcache behavior
  are not interchangeable.

Never embed a transient bundle size or timing in this skill. Re-measure it.

## Laravel, Database, and Inertia

- Look for N+1 queries, repeated aggregates, unbounded `get()` calls, PHP-side
  aggregation/sorting that belongs in SQL, non-sargable filters, and missing
  indexes on measured hot queries. Confirm index changes with the real query
  plan and production-compatible schema constraints.
- Select only required columns, eager-load required relationships, and paginate
  large result sets. Prefer `simplePaginate` or cursor pagination only when the
  resulting navigation and ordering semantics remain acceptable.
- Inspect Inertia prop size and computation. Consider partial reloads, optional
  or deferred props only after checking the installed Inertia version and only
  when loading behavior and component contracts remain correct.
- Cache only data with clear freshness and invalidation rules. Include every
  authorization, customer, locale, query, and pagination dimension in cache
  keys. Never allow cached data to cross customer or role boundaries.
- For `InventoryApiClient`, measure upstream calls and page counts. Avoid
  fetching and sorting an entire catalog when the upstream API can safely
  filter, sort, or paginate. Test HTTP changes with Laravel HTTP fakes and
  preserve failure semantics.
- Treat timeout, retry, queue, and asynchronous changes as behavior changes.
  Justify and test them rather than presenting them as automatic optimizations.
- For exports or other large streams, inspect database iteration and memory;
  consider chunking/lazy iteration only if output ordering and consistency are
  preserved.

## React and Vite

- Profile before adding `memo`, `useMemo`, or `useCallback`; these add their own
  cost and complexity. Stabilize identities only where profiler evidence shows
  avoidable work or where effect dependencies require it for correctness.
- Use stable keys and local state. Paginate or virtualize genuinely large lists,
  while preserving accessibility, keyboard interaction, row measurement, and
  table behavior.
- Check existing implementation before recommending a technique. This project
  already resolves page components through a lazy `import.meta.glob`, loads
  PDF.js dynamically in `PdfPreview`, and provides a virtualized table
  component. Confirm whether the affected screen actually uses those paths.
- Read Vite output by route and load path. A large optional worker or lazy chunk
  is different from a large initial bundle. `manualChunks` can improve caching
  but does not inherently reduce transferred bytes; use it only for a measured
  loading or cache problem.
- Optimize images, fonts, animations, and PDF rendering based on transfer,
  decode, CPU, or memory measurements. Preserve visual fidelity unless the user
  explicitly accepts a tradeoff.

## Runtime and Deployment

- Inspect the Dockerfile, Compose files, entrypoint, PHP-FPM, opcache, cache and
  queue drivers, and deployment documentation before recommending runtime
  changes.
- Verify whether Laravel configuration, route, event, and view caches are built
  or warmed in the deployed lifecycle. Do not add a cache command that conflicts
  with runtime environment injection or writable-volume behavior.
- Do not run migrations against the shared production database. Propose index or
  schema work separately with an explicit rollout and rollback plan.
- Do not add a service, dependency, queue worker, Redis, or monitoring product
  without explicit justification and authorization.

## Verification

Use the commands supported by the current environment. Typical checks are:

```text
npm.cmd run build
vendor/bin/pint --test <changed-php-files>
php artisan test <focused-tests>
php artisan test
```

When PHP is available only in the local container, use the repository's Compose
file, for example:

```text
docker compose -f docker-compose.local.yml exec -T app php artisan test
```

Also repeat the original performance measurement. For UI-sensitive changes,
smoke-test the affected interaction at representative viewport sizes. For data
or cache changes, test guest, admin/staff, customer, and orphaned-customer
authorization paths as applicable.

## Reporting

Lead with the measured outcome or, for a review, the highest-confidence risk.
For each finding include:

- impact and confidence;
- evidence and exact location;
- proposed technique and why it targets the measured cost;
- behavior, security, and operational risk;
- verification and rollback approach.

Separate confirmed bottlenecks from hypotheses that still need production or
browser evidence. Include commands actually run and their observed results. Do
not claim an improvement without a comparable before/after measurement.
