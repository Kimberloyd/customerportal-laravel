# Auditing an existing design against 60-30-10

Work through these in order — each step depends on having classified the design honestly in the step before it, so resist the urge to jump straight to "this needs a new accent color" before finishing the inventory.

## 1. Inventory every color actually in use

List every distinct color appearing in the design or theme/token file, with a rough sense of how much visual area/frequency each one covers. In code, this usually means grepping a CSS/theme file for hex values, CSS custom properties, or a design-token JSON, and noting which components reference each one. Don't skip colors that seem minor (a badge here, an icon there) — this is exactly where uncounted accents accumulate.

## 2. Classify what you actually have

For each color found, ask: is this behaving as dominant, secondary, accent, or semantic — regardless of what it was originally intended to be? A color meant as "just a secondary panel background" that also gets used on three different buttons across the product is functioning as a second accent, whatever its variable name says.

## 3. Check for the most common failure: too many equal-weight colors

Count how many colors are carrying accent-level visual weight (high saturation, used to draw attention or mark interactivity). If it's more than one or two, that's very likely the root cause of a design that feels busy or lacks a clear focal hierarchy, even if no individual color looks wrong in isolation. This is the single most common thing wrong with real-world palettes that were never deliberately built around the rule.

## 4. Check semantic isolation

Confirm error/success/warning/info colors aren't shared with the brand accent or with each other's hue family, and confirm they're used consistently for their meaning everywhere they appear (not "usually red for errors, but that one dialog uses orange for the same thing").

## 5. Check contrast on the pairings that exist today

Don't assume an existing design already passes WCAG — check the actual text/background and icon/background combinations in use. This is often where an audit finds real, user-affecting problems (light gray text on white, a "subtle" secondary button whose label barely clears the background) that a purely aesthetic review would miss.

## 6. Check dark mode independently, if it exists

A separate light/dark check matters even if the light mode passed every check above — dark mode is very often a mechanical inversion that was never independently verified, and it's common to find contrast failures or a muddy/harsh accent there even when light mode is solid.

## 7. Decide: consolidate, don't necessarily replace

The fix for a design with too many competing colors is usually to choose which of the *existing* colors gets promoted to each of the three (plus semantic) roles and demote or retire the rest — not to throw out the existing palette and invent a new one. Brand recognition and existing design equity matter; the goal is discipline in how the existing colors are deployed, not novelty. Note explicitly, when reporting findings, which colors are being kept in which role and which are being retired or restricted to occasional/legacy use, so the rationale is traceable later.
