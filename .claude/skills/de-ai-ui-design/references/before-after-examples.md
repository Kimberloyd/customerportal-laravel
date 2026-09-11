# Before/after examples (React + Tailwind)

These examples use React/Tailwind since that's the most common pairing for this kind of dashboard UI, but the underlying fixes are framework-agnostic -- the same changes apply in plain CSS, styled-components, or any other styling approach.

## Tell 1: The gradient

**Before:**

```jsx
<header className="bg-gradient-to-r from-violet-600 to-fuchsia-500 px-6 py-4">
  <h1 className="text-white font-bold">Dashboard</h1>
  <button className="bg-gradient-to-r from-violet-600 to-fuchsia-500 text-white rounded-lg px-4 py-2">
    Upgrade
  </button>
</header>
```

**After:**

```jsx
<header className="bg-neutral-900 px-6 py-4 border-b border-neutral-800">
  <h1 className="text-white font-bold">Dashboard</h1>
  <button className="bg-emerald-500 text-neutral-950 font-medium rounded-lg px-4 py-2">
    Upgrade
  </button>
</header>
```

One flat accent (`emerald-500` here) reused for the button, active nav state, and key highlights -- not a gradient sweep that doesn't represent anything.

## Tell 2: Icon tiles

**Before:**

```jsx
{stats.map((stat) => (
  <div key={stat.label} className="rounded-xl border border-neutral-800 p-4">
    <span className={`inline-flex rounded-full p-2 ${stat.iconBg}`}>
      {/* stat.iconBg is a different color per card: bg-blue-500/20, bg-green-500/20, etc. */}
      <stat.Icon className={stat.iconColor} />
    </span>
    <p className="text-sm text-neutral-400 mt-2">{stat.label}</p>
    <p className="text-2xl font-bold">{stat.value}</p>
  </div>
))}
```

**After:**

```jsx
{stats.map((stat) => (
  <div key={stat.label} className="rounded-xl border border-neutral-800 p-4">
    <p className="text-sm text-neutral-400">{stat.label}</p>
    <p className="text-2xl font-bold mt-1">{stat.value}</p>
    {stat.delta && (
      <p className="text-xs text-emerald-400 mt-1">{stat.delta}</p>
    )}
  </div>
))}
```

Dropped the per-card colored icon badge entirely -- the number and label carry the card now, with typography (not color-coding) doing the differentiation.

## Tell 3: Hierarchy

**Before:**

```jsx
<div className="grid grid-cols-2 gap-4">
  <StatCard label="Revenue" value="$48,250" />
  <StatCard label="Active users" value="2,340" />
  <StatCard label="Conversion" value="3.8%" />
  <StatCard label="Churn" value="1.2%" />
</div>
```

**After:**

```jsx
<div className="grid grid-cols-3 gap-4">
  {/* Primary metric: spans 2 columns, larger type, more supporting detail */}
  <div className="col-span-2 rounded-xl border border-neutral-800 p-5">
    <p className="text-sm text-neutral-400">Revenue</p>
    <p className="text-4xl font-bold mt-1">$48,250</p>
    <p className="text-sm text-emerald-400 mt-1">+12.5% from last month</p>
    <div className="flex gap-6 mt-4 pt-4 border-t border-neutral-800">
      <div>
        <p className="text-xs text-neutral-500">Subscriptions</p>
        <p className="text-sm font-medium">$41,900</p>
      </div>
      <div>
        <p className="text-xs text-neutral-500">One-time</p>
        <p className="text-sm font-medium">$6,350</p>
      </div>
    </div>
  </div>

  {/* Secondary metrics: smaller, stacked */}
  <div className="flex flex-col gap-3">
    <StatCard label="Active users" value="2,340" delta="+12.5%" compact />
    <StatCard label="Conversion" value="3.8%" delta="+12.5%" compact />
    <StatCard label="Churn" value="1.2%" delta="+12.5%" compact />
  </div>
</div>
```

One primary metric (larger, more space, supporting breakdown) and three secondary metrics rendered smaller -- not four identical tiles.

## Tell 4: Shadows

**Before:**

```jsx
<div className="rounded-xl bg-neutral-900 p-4 shadow-lg">
  {/* every card, input, and badge has shadow-lg or shadow-md */}
</div>
<input className="rounded-lg bg-neutral-900 px-3 py-2 shadow-md" />
<span className="rounded-full bg-neutral-800 px-2 py-1 text-xs shadow-sm">Paid</span>
```

**After:**

```jsx
{/* Static cards: border instead of shadow */}
<div className="rounded-xl bg-neutral-900 border border-neutral-800 p-4">
  ...
</div>
<input className="rounded-lg bg-neutral-900 border border-neutral-800 px-3 py-2" />
<span className="rounded-full bg-neutral-800 px-2 py-1 text-xs">Paid</span>

{/* Things that actually float: keep the shadow */}
<div role="menu" className="rounded-lg bg-neutral-900 border border-neutral-800 shadow-xl absolute top-full mt-2">
  {/* dropdown menu content */}
</div>
```

Shadow survives only on the dropdown/popover that's actually positioned above other content (`absolute`, layered) -- static cards and inputs in normal flow get a border instead.

## Tell 5: Copy

**Before:**

```jsx
<h2 className="text-lg font-semibold">Welcome back, {user.firstName} 👋</h2>
<StatCard label="Revenue" value="$48,250" delta="+12.5%" />
```

**After:**

```jsx
<h2 className="text-sm text-neutral-400">
  Revenue · {formatDateRange(period.start, period.end)}
</h2>
<div>
  <p className="text-4xl font-bold">{formatCurrency(revenue.total)}</p>
  <p className="text-sm text-emerald-400">
    +{revenue.percentChange}% vs {formatMonthShort(previousPeriod)}
  </p>
  <Sparkline data={revenue.dailySeries} />
</div>
```

Replaced the generic greeting with copy that states what the data actually is (a labeled date range) and a delta with a stated comparison basis (`vs Jul`, not just a bare badge), plus a sparkline that shows the number's actual shape over time instead of only a static delta.
