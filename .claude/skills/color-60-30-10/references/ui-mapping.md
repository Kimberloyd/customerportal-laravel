# Mapping roles to UI regions

## The standard mapping

| Role | Typical elements | Notes |
|---|---|---|
| Dominant (60%) | Page/app background, large content surfaces, the canvas most of the eye rests on | Should be nearly invisible as "a color" — its job is to not compete with anything |
| Secondary (30%) | Cards, panels, sidebars, headers/footers, secondary buttons, input field backgrounds, dividers, inactive nav items | Creates structure and grouping; distinguishable from the dominant but still calm |
| Accent (10%) | Primary CTA button, active/selected nav state, key links, progress indicators, focus rings, the one or two things per screen that most need the eye drawn to them | Reserved — if everything is accented, nothing is |
| Semantic (separate budget) | Error, success, warning, info states and their icons/borders/backgrounds | Never doubles as the brand accent; consistent meaning across the whole product |

## Why semantic colors need their own lane

Users learn color meaning fast and generalize it everywhere in the product: once red means "something's wrong" in one place, every other red thing in the UI inherits that association whether intended or not. Two consequences that matter in practice:

- If the brand accent happens to be red or close to it, either the accent needs to shift (a genuinely common and correct outcome — brand red often becomes a secondary or dominant-adjacent color rather than the interactive accent) or the semantic error color needs to be distinguishable enough (different hue family entirely, not just a different shade of red) that "primary button" and "something's broken" never look like siblings.
- The semantic set (typically red/error, green/success, amber-or-yellow/warning, blue/info) should be picked once, checked for contrast, and then left alone — introducing a fifth ad-hoc "special" status color for one feature breaks the learned vocabulary for users of the rest of the product.

## Dark mode adaptation

Don't invert values mechanically (swapping white for black and hoping saturation carries over) — rebuild each role's lightness deliberately:

- **Dominant:** true near-black (`L` 8-12%) reads as harsher and more fatiguing over long sessions than a very dark, slightly desaturated near-black with a hint of the palette's hue (`L` 10-15%, low S) — most professional dark themes use something closer to `#0F1115`-ish territory than pure `#000000`.
- **Secondary:** a step up in lightness from dominant (`L` 16-22%) rather than a step down — the relationship inverts from light mode (where secondary was often *darker* than a very light dominant) because "closer to black" no longer reads as "further back."
- **Accent:** saturated colors that work at `L` 45-55% on a white background frequently need to shift lighter (`L` 60-70%) to maintain the same perceived vibrancy and legibility against a dark surface — the same hex value often looks muddier or lower-contrast once the surrounding context goes dark, even though the hex itself hasn't changed.
- **Semantic colors:** same principle — error/success/warning hues typically need a lightness bump for dark mode to stay legible and to avoid looking desaturated/muddy against a dark background.

Treat light and dark mode as two palettes derived from the same role structure and hue relationships, not one palette with a filter applied.

## Contrast requirements (WCAG 2.x)

Check every pairing that actually occurs in the design, not just the "main" text color against the "main" background:

- **Normal body text** against its background: minimum 4.5:1 (AA). Aim for 7:1 (AAA) where the text is dense or the audience may include low-vision users.
- **Large text** (18pt+/24px+, or 14pt/18.66px+ bold) against its background: minimum 3:1.
- **UI components and graphical objects** that convey information (icon outlines, input borders, focus indicators, chart elements) against their adjacent color: minimum 3:1.
- **Never rely on color alone** to convey a state — an error field needs more than a red border (an icon, a text message, a label change) since colorblind users (roughly 1 in 12 men) may not perceive the color difference at all.

When a pairing fails: the fix is almost always adjusting lightness, not hue or saturation — darkening text or lightening a background by a few percentage points typically closes a contrast gap without visibly changing the palette's character. If closing the gap would require a change large enough to be visually obvious, that's a sign the original color choice needs to change, not just be nudged — don't ship a technically-adjusted color that no longer looks like it belongs to the palette.

## Common region-mapping mistakes

- **Multiple competing accents.** A page with a blue primary button, a green "new" badge, a purple premium-tier tag, and an orange notification dot has four things fighting for the 10% attention budget — the eye can't tell which one actually matters most. Pick one true accent for primary actions; let secondary indicators use more muted, secondary-role treatments (an outline instead of a fill, a smaller size) so they don't compete at the same visual weight.
- **Secondary doing double duty as accent.** Using the same color for "this card is slightly elevated" and "click this button" collapses the hierarchy the rule is meant to create — a user should be able to tell structural color from actionable color without reading the text.
- **Dominant that isn't actually 60% of what's visible.** If the background is dominant-colored but every third element on the page uses the secondary or accent color at similar size and frequency, the *effective* ratio is nowhere near 60-30-10 even if the design tokens are named that way. Judge by the rendered result, not the token names.
