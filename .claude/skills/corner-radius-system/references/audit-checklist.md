# Corner Radius Audit Checklist

Walk every rounded element in the component(s) under review against these six checks. For each hit, cite the actual values involved (not just "radius is inconsistent") and the specific fix.

## 1. Nested
- [ ] Does this element sit inside another rounded element, with padding between them?
- [ ] If so, does `inner_radius == outer_radius - padding`? If the inner element reuses the outer radius (or uses 0), flag it and compute the correct value.

## 2. Scale
- [ ] Does this element's radius come from the shared token scale (sm/md/lg/xl/full), or is it a one-off hardcoded value?
- [ ] Does the radius match the component's role per the scale (Chip/Badge -> sm, Button/Input -> md, Card -> lg, Modal/Sheet -> xl, Avatar/Toggle -> full)? Flag a Card using `full` or a Chip using `lg`, etc.

## 3. Pill
- [ ] Is `rounded-full` / `border-radius: 9999px` used anywhere?
- [ ] If so, is that element guaranteed single-line (chip, avatar, toggle)? If it's a card, panel, or anything that can hold multiple lines/rows, flag it and recommend a scale token instead.

## 4. Edges
- [ ] Is this element positioned flush (zero gap) against a container or viewport edge (sidebar against the left edge, sheet against the bottom, panel against the top)?
- [ ] If so, are the corners on that flush edge squared off (radius removed on that side), leaving only the corners with space beyond them rounded? Flag any flush edge that's still rounded.

## 5. Ring
- [ ] Is there a selection ring, focus ring, or highlight border drawn around a rounded element?
- [ ] Does the ring's radius equal `inner_radius + gap`, or does it just inherit/reuse the inner element's radius? A ring that pinches or looks slightly off at the corner is reusing the wrong value -- flag it and compute the correct ring radius.

## 6. Images
- [ ] Does this rounded card/container have an image flush to one or more of its edges?
- [ ] Does the image have `overflow: hidden` applied at the container level (clipping it to the container's radius), or does the image have its own correctly-computed radius (nested formula, if inset)? Flag a flush image with `radius: 0` and no clipping -- its square corner will poke out.

## Reporting format

For each violation found, state: the element, the specific radius values in play (e.g. "card `rounded-lg` = 8px, ring is also 8px"), which rule it violates, and the corrected value or fix (e.g. "ring should be 10px (8 + 2px gap), or wrap the ring in its own element with `rounded-[10px]`"). Then implement the fix, don't just report it.
