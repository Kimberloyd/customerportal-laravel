# Dashboard Content Audit Checklist

Use this to review an existing dashboard for concrete, fixable content and hierarchy problems -- adapted from Stephen Few's well-known catalogue of common dashboard design mistakes, applied specifically to content architecture rather than chart mechanics.

## Scope and decision-first architecture

- [ ] Can you state, in one sentence, the decision this dashboard supports? If not, the dashboard likely accumulated metrics without a governing purpose.
- [ ] Does the dashboard try to answer more than one unrelated core question on a single screen (e.g., overall business health *and* a specific campaign's performance)? That's usually a sign it should split into two dashboards.
- [ ] Is the primary audience and their review cadence evident from what's emphasized (an executive's 3-5 headline numbers vs. an analyst's denser working view)?

## Tiering and cognitive load

- [ ] Count the number of visualizations/metrics competing for attention on the primary view. More than roughly 5-9 at equal visual weight means nothing is actually prioritized -- everything is "important," which functionally means nothing is.
- [ ] Are there more than 5-7 metrics all treated as Tier 1 (large cards, top-row placement)? If so, the tiering hasn't actually been done -- it's a flat list styled to look tiered.
- [ ] Is granular/row-level detail (Tier 3) sitting directly on the main screen instead of behind a drill-down or separate view?

## Context (comparison points)

- [ ] Pick 3-5 headline metrics at random. Does each one show a comparison (prior period, target, or forecast), or is it a bare number with no way to tell if it's good or bad?
- [ ] Where a comparison exists, is the right type being used for that metric (target comparison for something with a hard quota/SLA, prior-period comparison for something being watched for trend)?
- [ ] Is data freshness/recency indicated anywhere, or does the viewer have to guess whether they're looking at real-time, this morning's, or last week's data?

## Status signaling

- [ ] Does any status indicator rely on color alone (a colored number or dot with no icon, label, or other non-color signal)? This is a common, easy-to-miss accessibility failure.
- [ ] Is the green/red convention consistent, and correctly inverted for metrics where lower is better (cost, churn, error rate, latency)?
- [ ] Is red reserved for things that genuinely need action, or is it used broadly enough that it no longer signals urgency?

## Layout and glanceability

- [ ] Does the primary view require scrolling to see the headline metrics? If so, it has crossed from "dashboard" into "report."
- [ ] Is the single most important metric positioned top-left (or at minimum, in the natural first-scan position), or is it buried mid-page or after navigation chrome?
- [ ] Is grouping achieved mainly through whitespace/proximity, or through heavy borders and background-color blocks on every section (visual noise that competes with the data itself)?
- [ ] Are filters in one consistent, predictable location, and do they apply globally across the whole view rather than only affecting some charts?
- [ ] Are filters framed in terms the user actually thinks in (date, customer, status, region), or in terms of internal system fields/IDs?

## Enhancement layer (drill-down, alerts, export)

- [ ] Do any alert banners or red/yellow/green thresholds exist without a clearly defined target behind them? An alert with no real threshold is decoration, not signal.
- [ ] Is drill-down interactivity present but the core Tier 1/2 hierarchy and context still missing or weak? That's a sign effort went into the enhancement layer before the fundamentals were solid -- flag this explicitly, since it's easy to mistake "feature-rich" for "well-designed."

## Presenting findings

Point to specific elements (which exact metric has no comparison, which section is Tier-3 detail sitting on the main screen, which status color has no accompanying icon) rather than a general "feels cluttered" reaction, and propose the specific fix -- which tier something belongs in, what comparison type to add, where it should move -- not just that something is off.
