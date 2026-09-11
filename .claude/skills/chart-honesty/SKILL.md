---
name: chart-honesty
description: "Use when building or reviewing any data visualization (bar/line/pie charts, dashboards, chart libraries like Recharts/Chart.js/D3/matplotlib) where the chart could mislead or feel arbitrary -- a bar axis that doesn't start at zero, a pie chart with more than ~5 slices, a chart type mismatched to the question being asked, colors used decoratively instead of to encode meaning, chart chrome (gradients, 3D, heavy gridlines, shadows) competing with the data, or a line chart whose aspect ratio exaggerates or flattens the trend. Applies a 5-part system: Zero Baseline (bar axes start at 0), Match the Form to the Question (bar=compare, line=change over time, pie only for few parts-of-a-whole), Color Encodes Meaning (highlight sparingly, use correct categorical/sequential/diverging palettes), Kill the Chartjunk (strip non-data ink), and The Aspect Ratio (bank line charts to read honestly). Use whenever building or auditing a chart or dashboard, even if the user doesn't say 'honest' or 'misleading.'"
---

# Chart Honesty

A chart is an argument. The same numbers, rendered two different ways, can tell two different stories -- and most of the time nobody intends to mislead, it just happens by default: a charting library truncates the y-axis to "zoom in," a designer picks a pie chart because it looks nicer than a bar, a palette gets applied because it's pretty rather than because it means something. This skill is a checklist for catching those defaults before they ship, whether you're building a chart from scratch or reviewing one that's already in a codebase.

The through-line across all five rules below: every visual choice in a chart should be justified by the data, not by habit or decoration. When you can't justify why an axis starts where it starts, why this chart type was picked over another, why this pixel is this color, or why the aspect ratio is what it is, that's the thing to fix.

## 1. Zero Baseline

Bar charts encode value as length -- the reader compares bar heights and trusts that a bar twice as tall means a value twice as large. That only holds if the axis starts at zero. Truncate the axis (start it at 40 instead of 0, say) and a 1-point gap between two bars can look like a landslide, because the visual ratio between the bars no longer matches the numeric ratio.

- Bar charts (and any chart using length/area as the primary encoding) must have a y-axis that starts at 0. This is not a stylistic preference -- it's the thing that makes the length encoding truthful.
- If the meaningful variation in the data is a small slice near a large baseline (revenue moving between 98,000 and 102,000, say), don't truncate the axis to make the bars look more dramatic. Either accept that the bars will look similar in height (because they ARE similar), or switch to a chart type built for showing small relative changes -- a line chart with the y-range chosen to show the trend, or a chart that explicitly shows the delta rather than the absolute values.
- Line charts are a different case: because position/slope is the encoding (not bar length from a baseline), a zoomed y-axis on a line chart isn't inherently dishonest the way a truncated bar axis is -- but see rule 5 (Aspect Ratio) for how the exact framing of a line chart can still mislead.

```jsx
// Dishonest: axis truncated to 46-49 makes a 1-point gap look like a landslide
<YAxis domain={[46, 49]} />

// Honest: axis starts at 0, the 1-point gap reads as what it is
<YAxis domain={[0, 'auto']} />
```

## 2. Match the Form to the Question

Every chart type is built to answer a specific kind of question. Picking one because it "looks more interesting" than the obvious choice is how charts stop communicating and start decorating.

- **Bar chart** -- compare discrete values across categories ("which region sold the most"). This is the default for comparison; reach for it first.
- **Line chart** -- show change over a continuous dimension, almost always time ("how did revenue trend over the year"). Don't use a line chart to connect unrelated categories (e.g. connecting "Region A, Region B, Region C" with a line implies an order/continuity that doesn't exist).
- **Pie/donut chart** -- show parts of a whole, and only when there are few enough slices to actually compare visually (roughly 5 or fewer). Human perception is bad at comparing angles/areas, so past ~5 slices a pie becomes a colorful list that a bar chart or table would communicate faster and more precisely. If you're reaching for a pie chart with 8+ categories, or with several slices that are close in size, switch to a horizontal bar chart sorted by value -- it makes the same "which is bigger" comparison actually legible.
- When reviewing a chart, ask what question it's trying to answer, then check whether the chart type actually answers that question efficiently. "The question picks the chart, not your taste" -- if you like the look of a donut chart more than a bar chart, that preference doesn't override what the data needs.

## 3. Color Encodes Meaning

Color in a chart should tell the reader something -- which category this is, whether this value is good or bad, which single data point matters right now. When color is applied for visual variety instead ("let's make each bar a different color so it's not boring"), it adds noise: the reader's eye tries to find meaning in the color differences and finds none.

- **Highlight sparingly.** If one bar/point/slice is the point of the chart (this month, the outlier, the answer to the question the chart is asking), give it the one saturated/brand color and render everything else in a muted neutral. This does more to focus attention than any amount of decoration -- it visually tells the reader "look here" before they've read a word of the caption.
- **Match the palette type to the data:**
  - *Categorical* (distinct, unordered groups -- product lines, regions): use visually distinct hues with no implied order.
  - *Sequential* (a single quantity from low to high -- intensity, count, percentage): use one hue ramping from light/muted to dark/saturated, so "more color" reads as "more value."
  - *Diverging* (values on either side of a meaningful midpoint -- profit/loss, above/below target): use two hues diverging from a neutral center, so the reader can tell direction (which side of the midpoint) at a glance, not just magnitude.
- Using a categorical rainbow palette for a sequential quantity (or vice versa) breaks the reader's ability to read the encoding correctly -- they'll try to rank colors that have no inherent order, or fail to see a "low-to-high" gradient that was color-coded as if it were unordered categories.

## 4. Kill the Chartjunk

Every pixel in a chart is either data or decoration. Gradients on bars, 3D bevels, drop shadows, heavy background gridlines, redundant borders and boxes -- none of it represents a number, and all of it competes with the pixels that do. The goal is a high **data-ink ratio**: the proportion of the chart's ink that's actually encoding data, versus ink that's just chrome.

- Strip gradients, 3D effects, and shadows from chart marks (bars, lines, points) by default -- flat fills and clean strokes read faster and don't introduce false depth cues that have nothing to do with the values.
- Gridlines should be a faint aid, not a competing pattern -- light enough to help the eye trace a value to the axis, not so heavy they visually compete with the bars/lines themselves. If a gridline is as visually loud as the data marks, it's too loud.
- Question every border, box-shadow, and background fill around the chart itself -- does it help the reader parse the data, or is it decorating the container the data lives in? A chart usually needs an axis, labels, and the marks -- everything beyond that should earn its place.
- This doesn't mean charts must be spartan or ugly -- it means every visual flourish should be deliberate. A single well-placed accent color, a subtle max-value line, a clean typographic label -- these carry meaning. A radial gradient behind every bar doesn't.

## 5. The Aspect Ratio

The exact same line-chart data can look nearly flat or dramatically spiky depending purely on how tall or wide the chart is drawn -- because the reader's perception of "trend" is driven by the visual slope of the line, and slope changes with aspect ratio even though nothing about the underlying data has changed.

- A very wide, short chart flattens every trend into a gentle slope, which can understate real volatility or growth.
- A very tall, narrow chart exaggerates every wiggle into a dramatic spike, which can overstate volatility or make routine variation look alarming.
- A well-established rule of thumb (banking to 45 degrees) is to size the chart so the average absolute slope of the line sits at roughly a 45-degree angle. This isn't an arbitrary aesthetic choice -- it's the aspect ratio at which human perception of angle-change is most sensitive and least distorted, so it gives the most honest read of "how much did this actually change."
- When building a chart component that will show variable data (different time ranges, different metrics), don't hardcode a fixed aspect ratio and call it done -- consider whether the ratio should adapt to the data's actual volatility so a flat quarter doesn't get visually inflated and a volatile one doesn't get visually smoothed away.

## Applying this skill

**When building a new chart from scratch:** work through the five rules as you choose chart type, axis config, color, and layout -- they're meant to be applied together, not bolted on after the fact. See `references/implementation-guide.md` for React/Recharts-flavored code patterns for each rule.

**When auditing existing charting code:** walk the code against all five rules systematically rather than fixing the first issue you spot and stopping. See `references/audit-checklist.md` for a per-rule checklist and the expected reporting format. Charts very often violate more than one rule at once (a truncated axis AND decorative colors AND a pie chart with 9 slices, all in the same dashboard) -- report everything you find, then fix it, don't just flag the first thing.
