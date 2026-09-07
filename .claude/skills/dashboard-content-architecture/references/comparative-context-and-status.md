# Comparative Context and Status Signaling

## Every metric needs a comparison point, chosen deliberately

A bare number is close to meaningless on a dashboard -- "1,204" tells a viewer nothing until it's placed against something. Stripe's home dashboard is a widely-cited example of doing this by default: every headline metric shows the current period's number with the prior period's number right beside it in smaller supporting text, so "is this good or bad" is answered without navigating anywhere else. Three comparison types cover almost every case; pick per metric, not once for the whole dashboard:

- **vs. prior period** (last week / last month / same period last year) -- use when the question is "which direction is this moving." Best default for metrics without a hard external target (weekly active users, session length, page views).
- **vs. target/goal** -- use when there's a defined number the metric is supposed to hit (a sales quota, an SLA threshold, a compliance rate). This is the right comparison whenever "on track or not" is a more useful question than "trending up or down."
- **vs. forecast** -- use when the organization already produces a forecast/plan for the metric and the operative question is variance from plan (revenue vs. forecasted revenue, headcount vs. planned headcount).

A metric can carry more than one (Stripe shows period-over-period; a finance dashboard often shows both target and forecast), but don't default to showing all three everywhere -- pick the one(s) that actually answer the question this metric exists to answer, and let the rest go in Tier 2/3 detail if someone wants it.

Always show data freshness/recency (a timestamp or "as of" label) alongside the comparison -- a viewer needs to know whether "current" means real-time, this morning's batch job, or last week's export before trusting the comparison at all.

## Status indicators: color is a signal, never the only signal

Relying on color alone to communicate status (green = good, red = bad) fails for a meaningful share of viewers -- roughly 1 in 12 men have some form of color vision deficiency, and color can also fail on a bad monitor, in bright light, or when printed/exported to grayscale. Every status signal needs a second, non-color channel:

- Pair color with a directional icon (an up/down arrow), not just a colored number.
- Pair a red/yellow/green status dot or badge with a text label ("At risk," "On track," "Off track"), not just the color alone.
- Keep the color convention consistent across the entire dashboard (and ideally the whole product): green always means "good/on-track," red always means "needs attention" -- and be explicit about directionality for metrics where lower is better (cost, churn, error rate), since a naive "up = green" rule inverts for those.
- Reserve red specifically for things that actually need action now. If red is used liberally (for anything below target, for any negative trend, for informational emphasis), it stops signaling urgency and the dashboard trains viewers to ignore it -- a variant of Few's "ineffective importance highlighting" mistake (everything prominent means nothing stands out).

## Setting thresholds before adding alerts

A red/yellow/green status or an alert banner is only meaningful once there's an actual defined threshold behind it (the target from Step 3, or an agreed-upon acceptable range). Building the visual alert mechanism before the threshold exists produces a dashboard that "looks reactive" but isn't actually telling anyone anything true -- the threshold decision has to come first.
