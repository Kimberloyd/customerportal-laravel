# Keyboard Focus & Accessibility -- Implementation Guide

Worked examples for all 5 rules. Examples use React + Tailwind; the same CSS/JS applies in any framework.

## Rule 1 -- Focus Ring

```css
/* Global reset that still leaves a compliant ring in place */
:focus {
  outline: none; /* remove the default UA outline styling quirks */
}
:focus-visible {
  outline: 2px solid #14B8A6;
  outline-offset: 2px;
}
```

Contrast check: if the element sits on a light background (`#F8FAFC`), a `#14B8A6` ring gives roughly 3.2:1 contrast -- compliant. On a dark background (`#0F172A`), the same ring color needs to be checked too; adjust the ring color per-surface if a single color doesn't clear 3:1 on both.

```jsx
// Tailwind arbitrary values, explicit width + offset
<input className="focus-visible:outline focus-visible:outline-[2px] focus-visible:outline-offset-2 focus-visible:outline-teal-500" />
```

## Rule 2 -- :focus-visible

```jsx
function Button({ children, ...props }) {
  return (
    <button
      className="rounded-md bg-slate-800 px-4 py-2 text-white
                 focus:outline-none
                 focus-visible:outline focus-visible:outline-2
                 focus-visible:outline-offset-2 focus-visible:outline-teal-500"
      {...props}
    >
      {children}
    </button>
  )
}
```

Plain CSS equivalent for a design system without Tailwind:

```css
.btn:focus {
  outline: none;
}
.btn:focus-visible {
  outline: 2px solid #14B8A6;
  outline-offset: 2px;
}
```

## Rule 3 -- Tab Order

```jsx
// Wrong: mobile layout reorders cards with CSS order, desktop DOM order stays the same
function Dashboard({ cards }) {
  return (
    <div className="grid grid-cols-2 md:grid-cols-4">
      {cards.map((c) => (
        <Card key={c.id} className={c.mobileOrderClass /* order-1, order-2, ... */}>
          {c.label}
        </Card>
      ))}
    </div>
  )
}

// Right: sort the data itself per breakpoint (or render two DOMs behind a media query
// hook) so Tab order always matches what's rendered
function Dashboard({ cards, isMobile }) {
  const ordered = isMobile
    ? [...cards].sort((a, b) => a.mobilePriority - b.mobilePriority)
    : cards
  return (
    <div className="grid grid-cols-2 md:grid-cols-4">
      {ordered.map((c) => (
        <Card key={c.id}>{c.label}</Card>
      ))}
    </div>
  )
}
```

If you must use CSS `order` for a purely cosmetic offset that doesn't change reading order (e.g. swapping which side an icon sits on within a single element), that's fine -- the rule is about ordering separate *focusable* elements relative to each other, not internal cosmetic layout.

## Rule 4 -- Focus Trap

```jsx
import { useEffect, useRef, useState } from 'react'

function useFocusTrap(active, onClose, triggerRef) {
  const containerRef = useRef(null)

  useEffect(() => {
    if (!active) return
    const container = containerRef.current
    const focusables = container.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    )
    const first = focusables[0]
    const last = focusables[focusables.length - 1]
    first?.focus()

    function onKeyDown(e) {
      if (e.key === 'Escape') {
        e.stopPropagation()
        onClose()
        triggerRef?.current?.focus()
        return
      }
      if (e.key === 'Tab' && focusables.length > 0) {
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault()
          last.focus()
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault()
          first.focus()
        }
      }
    }

    document.addEventListener('keydown', onKeyDown)
    return () => document.removeEventListener('keydown', onKeyDown)
  }, [active, onClose, triggerRef])

  return containerRef
}

function ConfirmDeleteModal({ open, onClose, onConfirm, triggerRef }) {
  const modalRef = useFocusTrap(open, onClose, triggerRef)
  if (!open) return null

  return (
    <div className="fixed inset-0 flex items-center justify-center bg-black/50">
      <div ref={modalRef} role="dialog" aria-modal="true" className="rounded-lg bg-slate-900 p-6">
        <h2 className="font-semibold text-white">Delete account?</h2>
        <p className="text-sm text-slate-400 mt-1">This removes all data. It cannot be undone.</p>
        <div className="mt-4 flex gap-2 justify-end">
          <button onClick={onClose}>Cancel</button>
          <button onClick={onConfirm} className="bg-red-500 text-white">Delete</button>
        </div>
      </div>
    </div>
  )
}

function AccountSettings() {
  const [open, setOpen] = useState(false)
  const triggerRef = useRef(null)
  return (
    <>
      <button ref={triggerRef} onClick={() => setOpen(true)}>Delete account</button>
      <ConfirmDeleteModal
        open={open}
        onClose={() => setOpen(false)}
        onConfirm={() => {/* ... */}}
        triggerRef={triggerRef}
      />
    </>
  )
}
```

## Rule 5 -- Skip Link

```jsx
function SkipLink() {
  return (
    <a
      href="#main-content"
      className="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[100]
                 focus:rounded-md focus:bg-teal-600 focus:px-4 focus:py-2 focus:text-white
                 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white"
    >
      Skip to main content
    </a>
  )
}

function App() {
  return (
    <>
      <SkipLink />
      <header>
        <nav>{/* many nav links */}</nav>
      </header>
      <main id="main-content" tabIndex={-1}>
        {/* page content */}
      </main>
    </>
  )
}
```

Plain CSS `sr-only` utility if not using Tailwind:

```css
.sr-only {
  position: absolute;
  width: 1px; height: 1px;
  padding: 0; margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
.sr-only:focus {
  position: fixed;
  width: auto; height: auto;
  margin: 0;
  overflow: visible;
  clip: auto;
}
```
