# Corner Radius System -- Implementation Guide

Worked examples for all 6 rules, in React + Tailwind. The same math applies to plain CSS or any other framework -- swap the class names for `border-radius` declarations.

## Token scale (set up once)

```js
// tailwind.config.js
module.exports = {
  theme: {
    extend: {
      borderRadius: {
        sm: '4px',   // Chip, Badge
        md: '8px',   // Button, Input
        lg: '12px',  // Card
        xl: '16px',  // Modal, Sheet
        // 'full' (9999px) already exists in Tailwind's default scale
      },
    },
  },
}
```

Plain CSS custom properties, if not using Tailwind:

```css
:root {
  --radius-sm: 4px;
  --radius-md: 8px;
  --radius-lg: 12px;
  --radius-xl: 16px;
  --radius-full: 9999px;
}
```

## Rule 1 -- Nested

```jsx
function ProjectCard({ project }) {
  return (
    // Outer: lg token (12px), 8px padding
    <div className="rounded-lg p-2 bg-slate-900">
      {/* Inner element: 12 - 8 = 4px, not 12px */}
      <div className="rounded-[4px] bg-slate-800 p-4">
        <h3 className="font-semibold">{project.name}</h3>
        <p className="text-sm text-slate-400">{project.org}</p>
      </div>
    </div>
  )
}
```

Compute it once as a small helper if it recurs across the codebase:

```js
function nestedRadius(outerPx, paddingPx) {
  return Math.max(outerPx - paddingPx, 0)
}
```

## Rule 2 -- Scale

```jsx
// Chip -- sm
<span className="rounded-sm px-2 py-1 text-xs bg-slate-800">Design</span>

// Button / Input -- md
<button className="rounded-md px-4 py-2 bg-emerald-500">Invite</button>
<input className="rounded-md px-3 py-2 border border-slate-700" />

// Card -- lg
<div className="rounded-lg bg-slate-900 p-4">...</div>

// Modal / Sheet -- xl
<div className="rounded-xl bg-slate-900 p-6 shadow-xl">...</div>

// Avatar / Toggle -- full
<img className="rounded-full w-10 h-10" src={avatar} />
<button role="switch" className="rounded-full w-11 h-6 bg-emerald-500" />
```

If a design calls for a radius not on the scale, treat that as a decision to extend the scale (add a token) rather than a one-off value on the component.

## Rule 3 -- Pill

```jsx
// Wrong: multi-line settings panel with rounded-full
function SettingsPanel() {
  return (
    <div className="rounded-full p-6"> {/* breaks once content wraps past one line */}
      <h2>Account settings</h2>
      <p>Manage your profile, billing, and notifications.</p>
    </div>
  )
}

// Right: multi-line content uses the scale, not full
function SettingsPanel() {
  return (
    <div className="rounded-xl p-6">
      <h2>Account settings</h2>
      <p>Manage your profile, billing, and notifications.</p>
    </div>
  )
}

// Right: single-line chip correctly uses full
function StatusChip({ label }) {
  return <span className="rounded-full px-3 py-1 text-xs bg-emerald-500/20 text-emerald-300">{label}</span>
}
```

## Rule 4 -- Edges

```jsx
// Bottom sheet anchored to the bottom of the viewport
function BottomSheet({ children }) {
  return (
    <div className="fixed bottom-0 inset-x-0 rounded-t-xl rounded-b-none bg-slate-900 shadow-xl">
      {children}
    </div>
  )
}

// Sidebar pinned to the left edge
function Sidebar({ children }) {
  return (
    <aside className="fixed left-0 inset-y-0 w-64 rounded-r-xl rounded-l-none bg-slate-900">
      {children}
    </aside>
  )
}

// Toast anchored to the bottom edge, floating (not flush) -- keeps all corners
function Toast({ message }) {
  return (
    <div className="fixed bottom-4 right-4 rounded-lg bg-slate-800 px-4 py-3 shadow-lg">
      {message}
    </div>
  )
}
```

The rule is about which edges the element is *flush against with zero gap* -- a toast with a margin from the screen edge (`bottom-4`, not `bottom-0`) has space beyond every corner, so it keeps full rounding.

## Rule 5 -- Ring

```jsx
function PricingCard({ plan, selected }) {
  // card radius: 8px (lg token in this design), ring offset: 2px
  // ring radius must be 8 + 2 = 10px, not 8px
  return (
    <div
      className={
        selected
          ? 'rounded-lg ring-2 ring-emerald-400 ring-offset-2 ring-offset-slate-950'
          : 'rounded-lg border border-slate-700'
      }
      style={selected ? { borderRadius: '8px' } : undefined}
    >
      {/* If the ring is drawn as a separate wrapper element rather than an outline,
          give the wrapper its own radius token explicitly: */}
    </div>
  )
}

// Ring drawn as an explicit outer wrapper (more control than Tailwind's ring utility)
function SelectableCard({ selected, children }) {
  return (
    <div
      className="p-[2px] rounded-[10px]" // 8 (card) + 2 (gap) = 10
      style={{ background: selected ? '#34d399' : 'transparent' }}
    >
      <div className="rounded-lg bg-slate-900 p-4">{children}</div>
    </div>
  )
}
```

## Rule 6 -- Images

```jsx
// Clip approach: image flush to the card's edges
function CoverCard({ image, title }) {
  return (
    <div className="rounded-xl overflow-hidden bg-slate-900">
      <img src={image} className="w-full h-40 object-cover" />
      <div className="p-4">
        <h3 className="font-semibold">{title}</h3>
      </div>
    </div>
  )
}

// Inset approach: image has its own padding, so it needs the nested radius (rule 1)
function InsetImageCard({ image, title }) {
  // card radius 16px (xl), image inset by 8px padding -> image radius 8px
  return (
    <div className="rounded-xl p-2 bg-slate-900">
      <img src={image} className="w-full h-40 object-cover rounded-[8px]" />
      <div className="p-4">
        <h3 className="font-semibold">{title}</h3>
      </div>
    </div>
  )
}
```
