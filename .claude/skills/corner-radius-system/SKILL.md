---
name: corner-radius-system
description: "Use when building or reviewing UI components (React/Tailwind, or any CSS) and corner radii look inconsistent or arbitrary -- nested elements whose inner corner bulges instead of nesting cleanly, a flat border-radius value pasted onto every component regardless of size, a full/pill radius used on a multi-line card, panels or sheets that stay rounded on the edge they're flush against, a selection ring that pinches at the card's corner, or an image with square corners poking out of a rounded card. Applies a 6-part radius system: Nested (inner = outer - padding), Scale (radius follows component size via tokens), Pill (full radius is only for single-line elements), Edges (no radius on an edge flush against a boundary), Ring (a ring adds to the inner radius, outer = inner + gap), and Images (clip images to the card's radius, don't leave them square)."
---

# Corner Radius System

Treat `border-radius` as a designed system, not a value you paste onto every element. A single flat radius (`border-radius: 8px /* everything */`) looks consistent from a distance but breaks the moment elements nest, scale, touch an edge, or get a ring around them. Six rules keep radius coherent across a component library.

## When to apply this

- **Building** a new component (card, sheet, modal, chip, pricing card, image card) that has any rounded corners, especially nested elements (padding + inner content) or a selection/focus ring.
- **Auditing** existing UI code for radius inconsistencies: search for `border-radius`, `rounded-*` Tailwind classes, and any hardcoded radius values, then check each against the six rules below.

## The 6 rules

### 1. Nested -- inner = outer − padding

When a rounded element sits inside another rounded element with padding between them, the two corners only look concentric (share one center) when:

```
inner_radius = outer_radius - padding
```

Example: an outer card with `border-radius: 12px` and `8px` padding needs an inner element radius of `4px`, not `12px` and not `0px`. Reusing the outer radius on the inner element makes the inner corner visibly bulge past where it should sit.

```jsx
// Wrong -- inner radius copies outer radius, corner bulges
<div className="rounded-xl p-2"> {/* 12px radius, 8px padding */}
  <div className="rounded-xl bg-slate-800">...</div> {/* also 12px -- wrong */}
</div>

// Right -- inner = outer - padding (12 - 8 = 4)
<div className="rounded-xl p-2">
  <div className="rounded-[4px] bg-slate-800">...</div>
</div>
```

If padding varies per side or isn't a clean token, round down rather than reuse the outer value -- a slightly-too-small inner radius reads fine; a too-large one visibly pinches.

### 2. Scale -- radius follows size, via tokens

Don't hardcode one radius everywhere. Map radius to component size/role with an explicit scale, and reuse it:

| Token | Value | Used by |
|---|---|---|
| `sm` | 4px | Chip, Badge |
| `md` | 8px | Button, Input |
| `lg` | 12px | Card |
| `xl` | 16px | Modal, Sheet |
| `full` | 9999px | Avatar, Toggle |

```js
// tailwind.config.js
theme: {
  extend: {
    borderRadius: {
      sm: '4px',
      md: '8px',
      lg: '12px',
      xl: '16px',
    },
  },
}
```

A component reaching for a one-off radius value (`rounded-[6px]`, `border-radius: 10px`) that isn't on the scale is a signal something should be normalized to the nearest token, or that the scale is missing a size it actually needs.

### 3. Pill -- full radius is a shape, not a default

`border-radius: 9999px` (or Tailwind's `rounded-full`) only reads correctly on elements that are exactly one line tall -- a chip, an avatar, a toggle. Apply it to anything with more than one line of content (a card, a panel, a multi-row list item) and the corners stop looking like a shape and just look broken, because the "radius" exceeds the element's own height on the short axis.

```jsx
// Wrong -- full radius on a multi-line card
<div className="rounded-full p-4">
  <h3>Aurora Handoff</h3>
  <p>Lumen Labs · Jonah Reyes</p>
  ...
</div>

// Right -- multi-line content gets a real numeric radius from the scale
<div className="rounded-xl p-4">
  <h3>Aurora Handoff</h3>
  <p>Lumen Labs · Jonah Reyes</p>
  ...
</div>
```

Rule of thumb: if the element can wrap to a second line or already has multiple lines/rows, it needs a real number (`sm`/`md`/`lg`/`xl`), not `full`.

### 4. Edges -- touching an edge means no corner there

A corner needs visible space beyond it to read as a corner. When an element is flush against the edge of its container or the screen (a sidebar against the viewport edge, a toast flush to the bottom, a bottom sheet anchored to the bottom of the screen), round only the corners that aren't touching a boundary -- square off the rest.

```jsx
// Wrong -- bottom sheet keeps all 4 corners rounded while flush to the bottom
<div className="fixed bottom-0 inset-x-0 rounded-xl">...</div>

// Right -- only the corners with space beyond them are rounded
<div className="fixed bottom-0 inset-x-0 rounded-t-xl rounded-b-none">...</div>
```

Same logic for a sidebar pinned to the left edge (`rounded-l-none`), or a toast anchored to a screen edge.

### 5. Ring -- outer = inner + gap, never inherit

A selection ring, focus ring, or highlight border drawn around an already-rounded element (like a selected pricing card) needs its own, larger radius -- it must not simply inherit the inner element's radius. If the ring sits `gap`px outside the card:

```
ring_radius = card_radius + gap
```

Example: a card with `border-radius: 8px` and a ring drawn `2px` outside it needs a ring radius of `10px`, not `8px`. A ring that inherits the card's radius pinches visibly at the corner instead of tracing a clean, concentric arc around it.

```jsx
// Wrong -- ring reuses the card's own radius, pinches at the corner
<div className="rounded-lg ring-2 ring-emerald-400 ring-offset-2">...</div>
{/* card radius 8px, ring offset 2px, but ring itself still computed at 8px */}

// Right -- ring radius = card radius + gap (8 + 2 = 10)
<div className="rounded-lg ring-2 ring-emerald-400 ring-offset-2 [--ring-radius:10px]">...</div>
```

In practice with CSS this usually means giving the ring wrapper its own explicit radius rather than relying on the inner element's radius to "show through."

### 6. Images -- clip to the card, don't leave them square

An image placed inside a rounded card, with `border-radius: 0` on the image itself, pokes a square corner out past the card's rounded edge wherever the image touches that corner. Two valid fixes:

- **Clip it**: put `overflow: hidden` on the card so the image is clipped to the card's own radius.
- **Subtract the padding**: if the image sits inset from the card edge by some padding, give the image its own radius using the Nested formula (rule 1): `image_radius = card_radius - padding`.

```jsx
// Wrong -- image has no radius, corner pokes out of the rounded card
<div className="rounded-xl">
  <img src={cover} className="w-full" />
  <div className="p-4">...</div>
</div>

// Right -- overflow-hidden clips the image to the card's radius
<div className="rounded-xl overflow-hidden">
  <img src={cover} className="w-full" />
  <div className="p-4">...</div>
</div>
```

If the image is inset (not touching the card edge), give it its own radius via the nested formula instead of overflow-hidden.

## Auditing existing code

When reviewing a component for radius issues, check each rounded element against all six rules in order: is it nested inside another rounded element (rule 1)? Does its radius come from the size/role scale (rule 2)? Is `full`/`rounded-full` used only on single-line elements (rule 3)? Does it touch a container or viewport edge without squaring that side (rule 4)? Does a ring or highlight border around it use its own larger radius (rule 5)? Does an image inside it clip to the container's radius (rule 6)? Report each violation with the specific values involved (e.g. "card is `rounded-lg` (8px) but ring uses the same 8px instead of 10px") and the fix, then implement the fixes.

## References

- `references/implementation-guide.md` -- full worked examples for all 6 rules in React/Tailwind, plus the token scale as a Tailwind config.
- `references/audit-checklist.md` -- a rule-by-rule checklist for reviewing existing components.
