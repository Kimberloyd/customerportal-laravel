# Layout Blueprint: Translating Tiers into Screen Regions

## The single-screen constraint

Stephen Few's dashboard research treats "exceeding single-screen boundaries" as one of the most common and damaging dashboard mistakes -- once a viewer has to scroll to see the primary metrics, the dashboard has stopped functioning as a dashboard (something monitored at a glance) and become a report (something read start to finish). Design the layout to fit Tier 1 + Tier 2 above the fold on a standard viewport, and treat "it doesn't fit" as a signal to cut content, not to shrink type until it does.

## The standard 3-region structure

A layout that consistently works across executive, analyst, and operational dashboards:

```
+---------------------------------------------------------------+
| Filters (date range, region, segment...)           [Export]   |  <- consistent, predictable location
+---------------------------------------------------------------+
| [KPI card]  [KPI card]  [KPI card]  [KPI card]  [KPI card]     |  <- Tier 1: 3-5 outcome KPIs,
|  value+Δ     value+Δ     value+Δ     value+Δ     value+Δ      |     top row, large type, top-left first
+---------------------------------------------------------------+
| Trend chart (line/area, time-series)   | Ranked breakdown       |  <- Tier 2: driver metrics,
| showing Tier 1 metric(s) over time     | (bar chart / list)     |     directly below/beside Tier 1
|                                         | by product/region/team |
+---------------------------------------------------------------+
| Secondary breakdowns / segment comparisons (Tier 2, continued) |
+---------------------------------------------------------------+
   -> Tier 3 (detail tables, granular filters) reached via drill-down,
      a separate tab, or a linked detail view -- not on this screen.
```

## Why top-left first

In left-to-right reading cultures, eye-tracking research on dashboards and web pages consistently shows an F-pattern or Z-pattern scan: the eye lands top-left first, sweeps right, then works down. Tier 1's single most important KPI should occupy that top-left position -- not centered, not buried after navigation chrome.

## Grouping: whitespace over decoration

Group related elements (a KPI card and the trend chart that explains it) through proximity and shared spacing rather than heavy borders, background-color blocks, or drop shadows on every panel. Few explicitly calls out "useless decoration" and "poorly designed display mechanisms" as common mistakes -- a light card boundary or consistent spacing rhythm is enough to signal grouping; a dashboard where every section has its own bold border and background tint reads as visually loud before any data is even parsed.

## Filter placement and design

- Put filters in one consistent, predictable location: a top bar (most common for a small number of filters) or a left rail (when there are many, or when filters themselves have some hierarchy).
- Frame filters the way users actually think about the data -- Stripe's dashboard filters by date, customer, and payment status (succeeded/failed/refunded), not by internal database fields or object IDs the user doesn't reason in.
- Keep the filter set global and applying consistently across the whole view (all charts respect the same date range) rather than having some charts respect filters and others not -- inconsistent filter scope is a frequent source of "the numbers don't add up" confusion.
- Don't over-build filter interactivity before the core hierarchy is solid -- a dashboard with twelve configurable filter dimensions and no clear Tier 1 is optimizing the wrong layer first.

## Navigation for multi-view dashboards

When a dashboard needs more than one screen (an overview plus specialized deep-dive views, as in Stripe's Payments/Payouts/Disputes/Customers structure, or Slack's Overview -> Channels -> Members tabbed structure), keep the same top-level hierarchy logic on each sub-view: a small Tier-1-equivalent header for that view's scope, driver detail below it. Consistent structure across views is what lets a viewer transfer their mental model from the home view to a specialized one without relearning the layout.
