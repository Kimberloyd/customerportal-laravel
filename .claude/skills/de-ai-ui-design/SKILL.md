---
name: de-ai-ui-design
description: "Implements and audits UI so it doesn't read as generically AI-generated, using a 5-part pattern: (1) one flat accent color instead of decorative gradients with no purpose; (2) letting the number/content be the visual hero instead of giving every stat card its own differently-colored icon badge; (3) real visual hierarchy (one primary metric, larger) instead of every card being the same size and weight; (4) shadows reserved for things that actually float (dropdowns, popovers, modals) instead of drop shadows on every static card/input/badge; (5) specific, functional copy instead of generic filler text like 'Welcome back, {name} 👋'. Use when building or reviewing a dashboard, admin panel, or app UI -- especially AI-generated interfaces, which commonly default to all five of these tells. Trigger when asked to make a UI look more polished/professional/less templated, when reviewing a screen for these issues, or when asked about a UI 'looking AI-made' or generic."
---

# De-AI Your UI

## The problem this fixes

AI-generated UI tends toward a recognizable house style: a gradient header with nothing behind it, four stat cards each with their own colored icon badge, every card the same size, a drop shadow on literally everything, and generic copy like "Welcome back, Jordan 👋". None of these are wrong individually -- they're default choices a model reaches for absent stronger direction -- but together they're what make a screen instantly read as templated rather than considered. Fixing all five is what actually changes the read; fixing one or two still leaves the rest as tells.

## Tell 1: The gradient with nothing to say

A gradient header or button that exists purely as decoration -- it doesn't represent anything (no status, no progress, no data), it's just "make it look premium." This is one of the most recognizable AI-UI signatures.

- Fix: use one flat accent color, applied consistently (the same color for the primary button, key highlights, and active states) rather than a gradient sweep across the header/button.
- A gradient is fine when it's *representing* something -- a data visualization, a genuinely branded hero moment used sparingly -- but not as generic chrome on every header and button by default.

## Tell 2: Icon tiles -- four colors, four numbers

Every stat card gets a small icon in its own colored badge (blue for revenue, green for users, purple for conversion, orange for churn) purely to differentiate the cards visually. The eye reads "four different colors" before it reads the actual numbers -- the colors are competing with the content for attention instead of supporting it.

- Fix: let the number be the visual hero. Drop the per-card color-coding; use a single neutral treatment for icons/labels across cards (or drop decorative icons from stat tiles entirely), and let typography (size, weight) do the differentiation instead of color.
- Reserve color for where it's semantically meaningful (a red for "over budget," a green for "on track") -- not applied uniformly just to distinguish otherwise-identical cards.

## Tell 3: Same size, same weight -- no hierarchy

Every card/tile in a group is rendered at the same size, so nothing signals which number is the one that actually matters. A dashboard where "Revenue," "Active users," "Conversion," and "Churn" are all visually equal treats them as equally important, which is rarely true.

- Fix: pick one primary metric and give it more visual weight -- larger type, more space, maybe a supporting trend/sparkline -- with the rest rendered smaller and secondary. "One primary, three (or more) secondary" rather than a uniform grid.
- This decision should follow from what the user of this screen actually needs to see first, not be arbitrary -- the primary metric is the one the page exists to answer, not just the first one in the data model.

## Tell 4: Shadows on everything that floats

Every card, input, and badge gets the same drop shadow, so the whole screen reads as a pile of floating panels instead of a page with actual depth relationships. When everything has the same elevation cue, elevation stops communicating anything.

- Fix: reserve shadow for elements that actually float above the page content -- dropdowns, popovers, modals, tooltips, a sticky header on scroll. Static content (cards, inputs, badges sitting in the normal page flow) gets a border or a subtle background-color difference instead, not a shadow.
- If a design system needs *some* depth cue on static cards, use it sparingly and consistently (e.g. a hairline border), not a shadow, which specifically communicates "this is floating above other content."

## Tell 5: Generic copy instead of specific, functional copy

Placeholder-style copy that a template would ship with by default -- "Welcome back, {name} 👋", a generic "+12.5%" badge with no comparison basis stated, section headers that don't say what the section actually is.

- Fix: write copy that states what the data actually is and means. "Revenue · Aug 1–31, 2026" instead of a generic greeting; "+12.5% from last month" or "+12.5% vs Jul" (a stated comparison basis) instead of a bare percentage badge; a numbers-with-a-shape treatment (a small trend line/sparkline next to the number) instead of just a colored delta badge repeated on every card.
- This isn't about being verbose -- it's about every piece of copy earning its place by conveying real information, rather than filling a slot a template expects to have text in.

See `references/before-after-examples.md` for concrete React/Tailwind before/after examples of all five, and `references/audit-checklist.md` for a systematic review procedure.

## The core principle

Each of these five defaults exists because it's a safe, generic choice -- decoration that doesn't require knowing anything about the actual product or data. Fixing them means replacing generic decoration with choices that reflect what this specific screen, for this specific data, actually needs to communicate. Direct your AI to apply all five together on a given screen -- fixing only one or two still leaves the rest reading as templated.
