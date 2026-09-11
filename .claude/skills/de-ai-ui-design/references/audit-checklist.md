# De-AI UI audit checklist

A systematic way to review an existing screen for the 5 tells. Go through all five, even if the first couple you check turn up nothing -- fixing only one or two tells still leaves a screen reading as templated.

## 1. Gradients

- [ ] Does any gradient on this screen (header, button, background) represent actual data, status, or brand identity used deliberately -- or is it decoration with nothing behind it?
- [ ] Is the same flat accent color reused consistently across primary buttons, active states, and key highlights, or is color chosen ad hoc per element?
- [ ] If multiple accent-colored elements exist, do they use the *same* accent, or does each component reach for its own color?

## 2. Icon tiles / color-per-card

- [ ] Does a group of similar cards/tiles (stat cards, list items, nav entries) each get its own distinct icon color purely to differentiate them from each other?
- [ ] Is color being used decoratively (just to tell cards apart) rather than semantically (status, category meaning)?
- [ ] Would removing the per-item color-coding and relying on typography/label still make each card's content clear? If yes, the color-coding wasn't earning its place.

## 3. Hierarchy

- [ ] In a group of metrics/cards, is there one that matters more than the others for this screen's purpose -- and does it look that way (size, weight, position), or is everything visually equal?
- [ ] Can a first-time viewer tell what the single most important number on this screen is within half a second, or do all the numbers compete equally?
- [ ] Does the "primary" treatment (if any) actually match what the screen's audience needs first, or was it picked arbitrarily (e.g. just "the first stat in the list")?

## 4. Shadows

- [ ] List every element with a shadow on this screen. For each one: does it actually float above other content (a dropdown, popover, modal, tooltip, sticky header)? Or does it sit in normal page flow (a card, an input, a badge)?
- [ ] Do static, in-flow elements use a shadow, a border, or both? If both, is the shadow adding anything the border doesn't already communicate?
- [ ] Is shadow intensity/usage consistent for elements at the same actual elevation, or does it vary without reason?

## 5. Copy

- [ ] Does any heading or label use generic template phrasing (a greeting with the user's name and an emoji, "Overview," "Summary," "Stats") instead of stating what the specific content is?
- [ ] Does every delta/comparison badge state its comparison basis (vs last month, vs target, vs the same period last year), or is it a bare percentage with an implied-but-unstated baseline?
- [ ] Where a number changes over time, is there any visual shape to it (a sparkline/mini-trend) or is it only ever shown as a single static value with a badge?

## Applying fixes

When multiple tells are present (which is the common case), fix them together rather than one at a time -- a screen with 4 of the 5 tells still reads as generic even after fixing 1. Treat this as a pass over the whole screen, not a sequence of isolated tweaks.
