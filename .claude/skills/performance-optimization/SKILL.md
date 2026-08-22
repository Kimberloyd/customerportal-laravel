---
name: performance-optimization
description: >
  Improve the speed and runtime performance of this full-stack app (React + Vite
  frontend, FastAPI proxy backend) WITHOUT changing behavior, UI, or breaking
  components. Use this skill whenever the user mentions "performance", "speed",
  "slow", "lag", "optimize", "faster", "responsiveness", "re-renders", "bundle
  size", "load time", "latency", "memory", or asks why something feels sluggish,
  or wants to profile / tune the app. Applies only safe, behavior-preserving
  techniques (memoization, code-splitting, caching, query tuning) and always
  verifies with a build/run afterward. Covers frontend rendering, network/data
  loading, bundling, and backend proxy/caching.
---

# Performance Optimization Skill

You optimize the speed and responsiveness of this application while **preserving
behavior, visual output, and component contracts**. The goal is to *utilize and
maximize* proven techniques — never to rewrite or risk working components.

Stack context: **frontend** is React 19 + Vite + Tailwind (components under
`frontend/src`, data via `useDashboardStats` / similar hooks, live updates over a
websocket). **Backend** is a FastAPI proxy (`backend/app/main.py`) that forwards
authenticated requests to an upstream inventory API.

---

## Prime Directive: Do No Harm

1. **Measure before changing.** Never optimize on a hunch. Identify the actual
   bottleneck (bundle report, React Profiler, Network tab, response timing) and
   state the evidence before touching code.
2. **Preserve behavior and UI exactly.** Same rendered output, same props, same
   API responses. If a change could alter behavior, it is out of scope for this
   skill — flag it instead.
3. **One optimization at a time**, each independently verifiable. After each,
   run `npm run build` (frontend) / `python -m py_compile app/main.py` (backend)
   and confirm the app still renders and endpoints still return the same data.
4. **Prefer reversible, low-risk wins first.** Memoization and lazy-loading
   before architectural changes.
5. **Don't add dependencies** unless clearly justified; prefer built-in APIs.

---

## Review & Optimization Dimensions

### 1. React Rendering
- **Unnecessary re-renders**: wrap expensive derived values in `useMemo`, event
  handlers passed to memoized children in `useCallback`, and pure leaf components
  in `React.memo`. Verify with the React Profiler that the render was actually
  hot first.
- **Stable identities**: avoid creating new object/array/function literals in
  render that feed memoized children or effect deps (a frequent re-render cause).
- **Key correctness**: stable, unique `key`s on lists (never array index if the
  list reorders) to avoid remount churn.
- **State placement**: keep frequently-changing state as local as possible so a
  small change doesn't re-render a large subtree. Lift only what must be shared.
- **Derive, don't duplicate**: compute from source state in render/`useMemo`
  instead of syncing into extra state via effects.

### 2. Lists & Heavy UI
- **Virtualize long lists** (100s+ of rows) so only visible rows render.
- **Paginate/window** large datasets rather than mounting everything.
- **Defer offscreen/expensive widgets** (charts, animations) until visible via
  `IntersectionObserver` or conditional mount.
- **Throttle/debounce** high-frequency handlers (scroll, resize, input, and the
  websocket-triggered refresh — this app already debounces the socket refresh).

### 3. Network & Data Loading
- **Parallelize independent requests** (`Promise.all`) — avoid waterfalls.
- **Abort stale requests** with `AbortController` on unmount / param change
  (already used in `useDashboardStats`).
- **Cache/dedupe** repeated identical fetches within a short window; reuse the
  last result instead of refetching on every minor change.
- **Avoid over-fetching**: request only the page/fields needed; keep `limit`
  probes small (the backend already uses `limit=1` count probes).
- **Coalesce refreshes**: batch rapid change events into one refetch (debounce).

### 4. Bundle & Build (Vite)
- **Inspect the bundle first** (`npm run build` reports chunk sizes; this project
  currently emits a >500 kB chunk — a real, measured target).
- **Code-split** heavy or route-level components with `React.lazy` +
  `Suspense`, and dynamic `import()` for rarely-used features (charts, calendars,
  drawers) so they don't load on first paint.
- **Tree-shake**: import only what's used (named imports, not whole namespaces);
  drop unused demo/example modules.
- **Configure `manualChunks`** to split large vendor libs (charts, animation,
  motion) out of the main bundle.
- **Assets**: prefer compressed/appropriately-sized images and lazy-load them;
  subset fonts if large.

### 5. Backend (FastAPI Proxy)
- **Reuse connections**: prefer a pooled async HTTP client (e.g. httpx AsyncClient)
  over per-request `urlopen`, so repeated upstream calls avoid new TCP/TLS setup.
- **Right-size timeouts** (this app separates `UPSTREAM_TIMEOUT_SECONDS` vs
  `REPORT_TIMEOUT_SECONDS`) — keep them tight enough to fail fast.
- **Cache stable upstream responses** briefly (in-memory TTL) for endpoints that
  don't change per-second, to cut latency and upstream load.
- **Bound pagination loops** and avoid fetching more pages than needed.
- **Consider async concurrency** for independent upstream calls a single route
  makes, mirroring the frontend's `Promise.all`.

---

## Output Format

When reviewing for performance, structure the response as:

### Summary
2–3 sentences: where the app most likely spends time and the highest-leverage,
lowest-risk win.

### Findings
For each opportunity:
- **Area** (Rendering / Lists / Network / Bundle / Backend)
- **Impact**: 🔴 High (user-visible latency) | 🟡 Medium | 🟢 Low
- **Evidence**: the measurement or code pattern that proves it's a bottleneck
- **Location**: `file:line` or component/function
- **Technique**: the specific safe optimization to apply
- **Risk & verification**: why it preserves behavior and how to confirm

### Recommended Order
Ranked list — cheapest/safest/highest-impact first.

---

## Safety Checklist (run before finishing)
- [ ] Rendered output and UI are visually identical.
- [ ] Component props/public contracts unchanged.
- [ ] API responses byte-for-byte equivalent (spot-check key endpoints).
- [ ] `npm run build` passes (frontend); backend compiles/imports.
- [ ] Each change is isolated and independently revertible.
- [ ] No new dependency added without explicit justification.
- [ ] The claimed improvement is backed by a before/after measurement.

---

## When NOT to optimize
- The code isn't actually hot (no measurement supports it) — don't add complexity.
- The optimization trades correctness, readability, or behavior for marginal gains.
- Premature micro-optimizations that obscure the code. Prefer clear code that is
  fast enough; reserve aggressive techniques for measured hot paths.
