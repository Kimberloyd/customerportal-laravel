# Content Hierarchy: The Three Tiers in Depth

## The underlying constraint

Cognitive-load research consistently cited across dashboard design practice puts effective at-a-glance processing at roughly 5-9 discrete pieces of information. Most failing dashboards aren't badly drawn -- they're trying to show 20-30 metrics with equal visual weight, which guarantees none of them actually register. The three-tier hierarchy exists to enforce a budget, not just to organize.

## Tier 1: Outcome KPIs (3-5 metrics)

These answer "how are we doing" at the level the dashboard's primary decision operates at. Characteristics:

- Tied directly to the decision identified in Step 1 -- if a metric doesn't inform that decision, it doesn't belong in Tier 1 even if it's important elsewhere.
- Shown as bold, single-value metric cards (large numeral, short label, comparison delta) -- not embedded inside a chart where the eye has to hunt for it.
- Positioned top-left to top-center, above everything else, visible without any interaction.
- Each one has a named owner in practice (someone whose job it is to move that number) -- if nobody owns a metric, it's a sign it may belong in Tier 2 (context) rather than Tier 1 (headline).

Example sets by dashboard type:
- **Executive/company dashboard**: revenue, active customers, gross margin, churn rate
- **Product/growth dashboard**: activation rate, weekly active users, retention (D7/D30)
- **Support/ops dashboard**: open ticket count, median first-response time, SLA compliance %
- **Finance dashboard** (mirrors patterns seen in tools like Stripe's own home view): net volume, successful payment rate, new customers, disputes open

## Tier 2: Supporting/driver metrics (8-12 metrics)

These exist to explain *why* Tier 1 moved, not to introduce new headline numbers. If Tier 1 says "revenue is down 8%," Tier 2 should let a viewer answer "down where" within the same screen:

- Categorical breakdowns: bar charts or ranked lists by product, region, channel, team -- whichever dimension the Tier 1 metric is usually diagnosed along.
- Trend charts: line/area charts showing the Tier 1 metric (or its direct drivers) over time, positioned directly below or beside the Tier 1 cards they explain.
- Segment comparisons: this cohort vs. that cohort, this channel vs. that channel.

Tier 2 is where most of the dashboard's real visual real estate goes, but it should still read as *subordinate* to Tier 1 -- smaller type, less saturated color, positioned below/beside rather than competing for the same top-left position.

## Tier 3: Diagnostic/detail (unbounded, mostly hidden)

Row-level data, edge cases, granular filters -- the material an analyst needs once Tier 2 has pointed at a specific suspect ("enterprise renewals in APAC slipped -- show me the individual accounts"). This tier should almost never appear on the primary dashboard view:

- Reached via drill-down click from a Tier 1/2 element, a dedicated "detail" tab, or a linked report -- not scrolled to.
- Can be as dense and tabular as needed -- exact values, full precision, sortable columns -- because a user who has drilled down has already opted into more detail and more time spent.

## Sorting a flat metric list into tiers -- worked method

When you're handed (or find) a flat list of "things this dashboard should show," sort each item by asking:

1. Does this directly answer the dashboard's core decision question? -> Tier 1 candidate.
2. Does this explain *why* a Tier 1 metric is moving, without answering the core question on its own? -> Tier 2.
3. Is this only useful once someone has already identified a specific problem to investigate? -> Tier 3.

If Tier 1 ends up with more than 5-7 candidates, that's a signal the dashboard is trying to answer more than one core question -- reconsider whether it should split into two dashboards (e.g., a "business health" view and a separate "campaign performance" view) rather than force everything onto one screen at reduced prominence.

## How this differs by audience

- **Executive**: mostly Tier 1, a thin Tier 2, essentially no Tier 3 on-screen (reference: strategic dashboards like Geckoboard's target use-case -- revenue, margin, engagement, glanceable).
- **Analyst/manager**: full Tier 1 + Tier 2, with fast, prominent paths into Tier 3 (reference: tactical dashboards like Databox's target use-case -- campaign performance, CAC, conversion rates, meant for someone acting on the data daily).
- **Operational/real-time (NOC, support queue)**: Tier 1 dominated by live status rather than historical trend, minimal Tier 2, Tier 3 often live-filterable tables rather than click-through (reference: operational dashboards like Bold BI's target use-case -- open tickets, response times, agent availability).
