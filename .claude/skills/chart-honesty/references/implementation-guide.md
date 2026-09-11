# Chart Honesty -- Implementation Guide

Code patterns for each rule, in React + Recharts (the patterns translate directly to Chart.js, D3, matplotlib/Plotly -- the underlying config is the same idea, just different API surface).

## 1. Zero Baseline

```jsx
// Bar chart -- always force the axis to include 0
<BarChart data={data}>
  <YAxis domain={[0, 'auto']} />
  <Bar dataKey="value" fill="#14b8a6" />
</BarChart>
```

Never pass an explicit lower bound above 0 to a bar chart's value axis (`domain={[40, 100]}`, a manual `min` on a Chart.js scale, `ylim(40, 100)` in matplotlib for a bar plot). If a charting library's default behavior auto-scales the axis to the data's min/max (several do, "to use the space better"), override it explicitly -- the honest default has to be set on purpose.

If the real story is a small change against a large baseline, don't fight the chart type -- switch to one built for that:

```jsx
// Instead of truncating a bar axis to dramatize a 98,000 -> 102,000 move,
// show the trend as a line (where a zoomed range is legitimate) or show the
// delta directly.
<LineChart data={monthlyRevenue}>
  <YAxis domain={[95000, 105000]} />
  <Line dataKey="revenue" stroke="#14b8a6" />
</LineChart>
```

## 2. Match the Form to the Question

A simple decision order when picking a chart type:

1. Is this comparing discrete categories? -> bar chart.
2. Is this showing change across a continuous dimension (almost always time)? -> line chart.
3. Is this showing parts of a whole, with 5 or fewer parts? -> pie/donut is acceptable.
4. Is this showing parts of a whole with more than 5 parts? -> horizontal bar chart, sorted descending by value.

```jsx
// A pie chart pushed past its limit -- 9 categories are unreadable as angles
<PieChart data={nineCategories} /> // don't do this

// Sorted horizontal bar communicates the same "which is biggest" comparison,
// legibly, at any category count
<BarChart data={nineCategories.sort((a, b) => b.value - a.value)} layout="vertical">
  <XAxis type="number" domain={[0, 'auto']} />
  <YAxis type="category" dataKey="name" />
  <Bar dataKey="value" fill="#14b8a6" />
</BarChart>
```

Watch for a line chart connecting categories that have no inherent order or continuity (e.g. `<LineChart data={[{name: 'Region A', ...}, {name: 'Region B', ...}]}>`) -- the connecting line visually implies a trend/sequence between unrelated categories. Use a bar chart there instead.

## 3. Color Encodes Meaning

```jsx
// Highlight the one bar that matters, mute the rest
const highlightIndex = data.findIndex(d => d.month === 'Jul')
<Bar dataKey="value">
  {data.map((entry, i) => (
    <Cell key={i} fill={i === highlightIndex ? '#14b8a6' : '#64748b'} />
  ))}
</Bar>
```

Palette selection by data type:

```js
// Categorical -- distinct hues, no implied order
const categorical = ['#14b8a6', '#a78bfa', '#64748b', '#1e293b', '#94a3b8']

// Sequential -- one hue, light to dark, "more color = more value"
const sequential = ['#134e3a', '#0f766e', '#14b8a6', '#5eead4', '#99f6e4']

// Diverging -- two hues from a neutral center, for +/- around a midpoint
const diverging = ['#f43f5e', '#fca5a5', '#94a3b8', '#5eead4', '#14b8a6']
```

Pick the palette from the data's shape, not from what looks good in isolation: a `status` field with no order is categorical, a `percentComplete` field is sequential, a `varianceFromTarget` field that can be positive or negative is diverging.

## 4. Kill the Chartjunk

```jsx
// Chartjunk-heavy
<Bar dataKey="value" fill="url(#gradient)" stroke="#000" strokeWidth={2}
     style={{ filter: 'drop-shadow(0 4px 6px rgba(0,0,0,0.3))' }} />
<CartesianGrid strokeWidth={2} stroke="#475569" />

// Stripped to data-ink
<Bar dataKey="value" fill="#14b8a6" radius={[4, 4, 0, 0]} />
<CartesianGrid strokeDasharray="3 3" stroke="#1e293b" vertical={false} />
```

A quick self-check: for every style property set on a chart element, ask "does this represent something about the data, or is it decoration?" A `fill` that maps to a category is data-ink. A `boxShadow` on the chart's containing `<div>` almost never is.

## 5. The Aspect Ratio

```jsx
// Instead of a fixed height regardless of data volatility, size the chart so
// its average slope reads near 45 degrees -- rough heuristic: scale the
// container height to the data's value range relative to its width.
function bankedHeight(data, width, targetAngleDeg = 45) {
  const values = data.map(d => d.value)
  const range = Math.max(...values) - Math.min(...values)
  const dataWidth = data.length - 1
  // height such that (range / height) / (dataWidth / width) ~= tan(targetAngleDeg)
  const targetSlope = Math.tan((targetAngleDeg * Math.PI) / 180)
  return (range * width) / (dataWidth * targetSlope)
}
```

You don't need this level of precision in every dashboard, but when a line chart's container has a fixed aspect ratio applied uniformly across very different metrics (a flat, stable metric and a highly volatile one both rendered in the same `16:9` box), that's worth flagging -- the flat one will look artificially dramatic-or-not depending purely on the box, not the data.
