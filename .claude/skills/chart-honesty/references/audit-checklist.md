# Chart Honesty Audit Checklist

Walk the charting code under review against all five rules below. Charts frequently violate more than one at once -- don't stop at the first hit, check every rule against every chart in the file(s) under review.

## 1. Zero Baseline
- [ ] For every bar chart, does the value axis have an explicit `domain`/`min`/`ylim` starting at 0?
- [ ] If no explicit domain is set, does the library's default auto-scale to the data's min (which can silently truncate)? Flag this even if it "looks fine" on the current data -- it will misrepresent a future dataset with a smaller relative range.
- [ ] Flag any bar chart with a domain lower bound above 0 as a truncated axis, and explain how it distorts the visual comparison.

## 2. Match the Form to the Question
- [ ] For each chart, what question is it answering (compare categories / show change over time / show parts of a whole)? Does the chart type match?
- [ ] Any pie/donut chart with more than ~5 slices, or with several similarly-sized slices? Flag it and propose a sorted horizontal bar chart instead.
- [ ] Any line chart connecting discrete, unordered categories rather than a continuous dimension like time? Flag the implied continuity/order that isn't real.

## 3. Color Encodes Meaning
- [ ] Are colors assigned to chart marks with a clear data-driven rule (a fixed categorical palette, a sequential ramp, a diverging scale, a single highlight color), or are they assigned arbitrarily (random hues, "whatever looked good," a rainbow with no logic)?
- [ ] If one data point is the point of the chart (the current period, the outlier, the answer to the chart's implied question), is it visually distinguished from the rest, or does everything have equal visual weight?
- [ ] Does the palette type match the data type (categorical data get a categorical palette, an ordered/quantity field gets sequential, a positive/negative-around-a-midpoint field gets diverging)? Flag a mismatch (e.g. a rainbow categorical palette applied to a sequential percentage field).

## 4. Kill the Chartjunk
- [ ] Do chart marks (bars/lines/points) use gradients, 3D effects, or drop shadows that don't encode anything about the data?
- [ ] Are gridlines heavier/darker than they need to be to faintly guide the eye -- do they visually compete with the data marks?
- [ ] Is there decorative chrome around the chart itself (heavy borders, background fills, redundant boxes) that doesn't help the reader parse the data?
- [ ] For each flagged style property, can you name what data value it represents? If not, it's chartjunk.

## 5. The Aspect Ratio
- [ ] Is the chart's aspect ratio fixed regardless of the data's actual volatility/range, applied uniformly across charts showing very different metrics?
- [ ] Would the same data, rendered at a different aspect ratio, tell a visibly different story (a trend that reads as flat vs. dramatic purely from the box shape)? If the component is reused across metrics with different volatility, flag that the fixed ratio can misrepresent some of them.

## Reporting format

For each violation found, state: the component/file, the specific code involved (e.g. `"RevenueChart.jsx` sets `<YAxis domain={[46, 49]} />` on a bar chart, truncating the axis"`), which rule it violates, and the concrete fix. Then implement the fix, don't just report it.
