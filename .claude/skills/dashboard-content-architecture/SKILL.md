---
name: dashboard-content-architecture
description: "Decides what content belongs on a dashboard and where, with a BI-designer's judgment -- decision-first scope, a real three-tier hierarchy (outcome KPIs, driver metrics, diagnostic detail), comparative context per metric (vs. prior period/target/forecast), redundant status signaling, and a single-screen F-pattern layout with disciplined filtering and progressive disclosure for drill-downs/alerts/exports. Use when designing, building, or reviewing a dashboard's CONTENT and layout -- what metrics/charts go where, structuring an executive/analyst/operational dashboard, or auditing one that feels cluttered or missing context. Covers content architecture, not chart-type styling or color palette (use a dedicated visualization/color skill for those). Also trigger for too many equally-prominent metrics, KPIs with no comparison point, color-only status, or 'what should be on this dashboard'."
---

# Dashboard Content Architecture

## Why most dashboards fail before the first chart is drawn

The most common dashboard failure isn't a bad chart -- it's an undisciplined list of "everything we could measure" with no decision about what matters most, shown with no comparison point, competing for attention with everything else on the page. Stephen Few's research on dashboard design and the "5-second rule" used across BI practice agree on the same underlying constraint: humans can reliably process roughly 5-9 pieces of information at a glance, and a dashboard that isn't scoped to that limit stops being a dashboard and becomes a report. Content architecture -- deciding what goes on the page, in what priority, with what context -- has to happen before any chart-type or color decision, and it's the step most dashboards skip.

## Step 1: Start from the decision, not the data

Before choosing a single metric, answer three questions, because they determine everything downstream:

1. **What decision does this dashboard need to support?** ("Should we increase ad spend this week," not "how is marketing doing.") A dashboard with no decision behind it accumulates metrics indefinitely.
2. **Who is the primary audience, and what's their review cadence?** An executive glancing once a week needs 3-7 outcome metrics and nothing else on first view. An analyst working the data daily needs the diagnostic layer surfaced faster, even if it costs some visual restraint. A NOC/ops team watching in real time needs live status and minimal historical context.
3. **What's the single core question this view answers?** A dashboard that tries to answer multiple unrelated questions on one screen (e.g., "are we profitable" and "which support tickets are stuck") should usually be two dashboards, not one with a low ceiling on both.

If you're auditing an existing dashboard rather than building one, reconstruct these three answers from what's there before judging anything else -- most of what looks like a "layout problem" is actually a scope problem.

## Step 2: Build a real three-tier content hierarchy

Don't treat every metric as equally important. Use an inverted-pyramid structure with three tiers, each with a distinct role and a size budget:

- **Tier 1 -- Outcome KPIs (3-5 metrics, must-have).** The "how are we doing" numbers tied directly to the decision this dashboard supports (revenue, active users, error rate, whatever the North Star is for this audience). These get the most prominent real estate -- large single-value cards, high-contrast typography, positioned where the eye lands first.
- **Tier 2 -- Supporting/driver metrics (8-12 metrics, should-have).** These explain *why* the Tier 1 numbers are moving -- the categorical breakdowns and trend charts (by product, by region, by channel) that turn "revenue is down" into "revenue is down because enterprise renewals slipped." Positioned below or beside Tier 1, still visible without scrolling on the primary view.
- **Tier 3 -- Diagnostic/detail (unlimited, nice-to-have, mostly hidden by default).** Row-level data, granular breakdowns, and edge cases that answer "why exactly" once Tier 2 has pointed at a suspect. This tier belongs behind a drill-down click or a separate detail view, not on the main screen -- putting it there is the single most common way a dashboard blows its 5-9-item budget.

See `references/content-hierarchy-tiers.md` for how this maps differently across executive, analyst, and operational dashboards, and worked examples of sorting a flat metric list into the three tiers.

## Step 3: Give every metric comparative context -- this is not optional

A number with no comparison point answers nothing. "1,204 signups" doesn't tell anyone if that's good; "1,204 signups, +12% vs. last week, 80% of this month's target" does. Every Tier 1 and Tier 2 metric needs at least one of:

- **vs. prior period** (last week, last month, last year) -- shows direction of travel
- **vs. target/goal** -- shows whether performance is where it needs to be
- **vs. forecast** -- shows whether you're tracking to plan

Pick the comparison type deliberately per metric rather than defaulting to the same one everywhere -- a metric with a hard target (SLA compliance, a sales quota) wants target comparison; a metric being watched for trend (weekly active users) wants prior-period comparison. See `references/comparative-context-and-status.md` for the decision rules and for status-indicator conventions (why color alone is not enough).

## Step 4: Design the physical layout as a single-screen blueprint

Translate the tiers into a real screen layout, not just a priority list:

- **Top-left first.** In left-to-right reading cultures, eyes land top-left first (F-pattern/Z-pattern scanning) -- put Tier 1 there.
- **Fit on one screen.** A dashboard that requires scrolling to see its own primary metrics breaks the "glance" premise that makes it a dashboard rather than a report. If Tier 1 + Tier 2 don't fit above the fold, the tiering is too generous -- cut, don't shrink everything to fit.
- **Group with whitespace, not decoration.** Related metrics get proximity and shared framing (a card, a bordered section); unrelated metrics get separation. Avoid heavy borders/backgrounds as the primary grouping mechanism -- they add visual noise Few explicitly warns against ("useless decoration," "poorly designed display mechanisms").
- **Filters go in one consistent, predictable place** (top bar or a left rail) and should be framed the way users actually think about the data (date range, customer, status, region) rather than internal system fields the user doesn't reason in.

Full layout blueprint (grid regions, filter placement, spacing) in `references/layout-blueprint.md`.

## Step 5: Treat drill-down, alerting, and export as an enhancement layer

These are real value-adds, but they're Tier-3-adjacent: they should be built *after* the core hierarchy and context are right, not instead of them. A dashboard with sophisticated drill-downs and alert banners but no comparative context on its top-line KPIs has its priorities backwards.

- **Drill-downs**: click a Tier 1/2 summary to reveal the Tier 3 rows behind it -- this is how Tier 3 stays off the main screen without being unreachable.
- **Alerting**: visual threshold banners (a metric crossed a red/yellow boundary) are valuable, but only once the metric already has a defined target/threshold from Step 3 -- an alert with no defined threshold is just noise with a red background.
- **Export**: a simple CSV/PDF download affordance near the relevant view, not a prominent primary action.

## Auditing an existing dashboard

When reviewing rather than building, walk through Steps 1-5 in order against what's actually there, and use `references/audit-checklist.md` -- it's organized around Stephen Few's well-known catalogue of dashboard design mistakes (exceeding one screen, missing context, inconsistent visual language, color-only status, poor prioritization) applied concretely to layout and content choices. Point at specific elements (which metric has no comparison, which section is competing with Tier 1 for attention) rather than giving a general "feels busy" impression, and propose the specific fix -- which tier something belongs in, what comparison to add -- not just that something is wrong.
