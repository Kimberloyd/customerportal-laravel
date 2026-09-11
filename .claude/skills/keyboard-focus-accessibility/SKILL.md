---
name: keyboard-focus-accessibility
description: "Use when building or reviewing interactive UI (buttons, forms, modals, navigation, custom widgets) and keyboard focus handling looks missing or broken -- outline: none with no replacement, a focus ring that shows on mouse click as well as keyboard tab, tab order that doesn't match the visual layout because elements were reordered with CSS instead of the DOM, a modal where Tab escapes to the page behind it or Escape doesn't return focus to the trigger, or a site with no way to skip repeated navigation links before the main content. Applies a 5-part system: Focus Ring (never remove outline without a compliant replacement: 2px width, 2px offset, 3:1 contrast), :focus-visible (ring shows for keyboard only, not mouse clicks), Tab Order (visual order must match DOM order), Focus Trap (Tab cycles inside an open modal; Escape returns focus to the trigger), and Skip Link (a visible-on-focus link past repeated navigation)."
---

# Keyboard Focus & Accessibility

Focus is a feature, not an afterthought. A sighted mouse user never notices whether Tab order matches the layout or whether a modal traps focus correctly -- but for a keyboard-only or screen-reader user, these are the entire interface. Five rules keep keyboard navigation usable and WCAG-compliant.

## When to apply this

- **Building** any interactive component: buttons, links, form inputs, modals/dialogs, dropdown menus, custom widgets, or a page layout with navigation.
- **Auditing** existing UI code for keyboard-accessibility gaps -- especially AI-generated UI, which commonly sets `outline: none` for a "cleaner" look without checking what it removes, or forgets tab order/focus trap/skip link entirely because they're invisible to a mouse-only review.

## The 5 rules

### 1. Focus Ring -- never remove it without a compliant replacement

`outline: none` (or `outline: 0`) on a focusable element with no replacement is a WCAG 2.4.7 (Focus Visible) failure -- it makes the page unusable for keyboard users, who lose all visual indication of where they are.

```css
/* Wrong -- ring removed, nothing replaces it */
button:focus {
  outline: none;
}

/* Right -- explicit, compliant replacement */
button:focus-visible {
  outline: 2px solid #14B8A6;
  outline-offset: 2px;
}
```

A compliant ring needs:
- **2px minimum width** (thin rings are easy to miss, especially for low-vision users)
- **2px offset** from the element edge, so the ring doesn't visually merge with the element's own border/background
- **At least 3:1 contrast** against the adjacent background color (WCAG 1.4.11) -- check this against both light and dark surfaces the component can appear on, not just one

```jsx
// React / Tailwind
<button className="focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-500">
  Sign in
</button>
```

If a design genuinely wants to change the ring's look (not remove it), that's fine -- style it, don't delete it. Never ship `outline: none` alone.

### 2. :focus-visible -- ring for those who need it

Use the `:focus-visible` pseudo-class instead of plain `:focus`. `:focus` fires on both mouse clicks and keyboard navigation, so a plain `:focus` ring shows an unnecessary ring every time a mouse user clicks a button. `:focus-visible` lets the browser decide -- keyboard/programmatic focus gets the ring, a mouse click generally doesn't.

```css
/* Wrong -- ring shows on every click, including mouse clicks */
button:focus {
  outline: 2px solid #14B8A6;
}

/* Right -- ring shows only for keyboard/non-pointer focus */
button:focus-visible {
  outline: 2px solid #14B8A6;
}
```

```jsx
// React / Tailwind -- use focus-visible: variant, not focus:
<button className="focus-visible:outline focus-visible:outline-2 focus-visible:outline-teal-500 focus:outline-none">
  Subscribe
</button>
```

Don't drop the ring entirely for mouse users by using `:focus` + `outline: none` reset tricks -- use `:focus-visible` so the browser's own heuristic handles it correctly.

### 3. Tab Order -- focus follows the DOM

Tab order follows the document's source order, not its visual/CSS order. If elements are visually reordered with CSS (`order` in flexbox/grid, `float`, absolute positioning) without reordering the underlying DOM, Tab will jump around in an order that no longer matches what's on screen -- confusing and disorienting for keyboard users.

```jsx
// Wrong -- visually reordered with CSS order, DOM order stays original
// Tab order (DOM): Revenue(1) -> Orders(2) -> Customers(3) -> Refunds(4)
// Visual order: Revenue -> Orders -> Refunds -> Customers (because Refunds has order: 2)
<div className="grid grid-cols-2">
  <Card className="order-1">Revenue</Card>
  <Card className="order-3">Orders</Card>
  <Card className="order-4">Customers</Card>
  <Card className="order-2">Refunds</Card> {/* visually 2nd, but 4th in the DOM */}
</div>

// Right -- DOM order matches visual order, no CSS order needed
<div className="grid grid-cols-2">
  <Card>Revenue</Card>
  <Card>Refunds</Card>
  <Card>Orders</Card>
  <Card>Customers</Card>
</div>
```

If a responsive layout genuinely needs different visual arrangements at different breakpoints, prefer reordering the actual array/JSX per breakpoint (or restructuring the grid) over `order`, so Tab always matches what's visible.

### 4. Focus Trap -- lock focus inside an open modal

When a modal/dialog opens, keyboard focus should be trapped inside it: Tab and Shift+Tab should cycle only among the modal's own focusable elements, never leaking out to the page behind it (which is often visually hidden or inert anyway). Escape should close the modal and return focus to the element that opened it -- not leave focus lost on `<body>`.

```jsx
import { useEffect, useRef } from 'react'

function Modal({ triggerRef, onClose, children }) {
  const modalRef = useRef(null)

  useEffect(() => {
    const modal = modalRef.current
    const focusable = modal.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    )
    const first = focusable[0]
    const last = focusable[focusable.length - 1]
    first?.focus()

    function handleKeyDown(e) {
      if (e.key === 'Escape') {
        onClose()
        triggerRef.current?.focus() // hand focus back to whatever opened the modal
        return
      }
      if (e.key !== 'Tab') return
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault()
        last.focus()
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault()
        first.focus()
      }
    }

    modal.addEventListener('keydown', handleKeyDown)
    return () => modal.removeEventListener('keydown', handleKeyDown)
  }, [onClose, triggerRef])

  return (
    <div ref={modalRef} role="dialog" aria-modal="true">
      {children}
    </div>
  )
}
```

In practice, prefer a battle-tested primitive (Radix Dialog, Headless UI, `<dialog>` with polyfill behavior) over hand-rolling this when one is available -- but if reviewing hand-rolled modal code, check for exactly these two behaviors: Tab cycling stays inside, and Escape returns focus to the trigger.

### 5. Skip Link -- jump the header in one press

Add a "Skip to main content" link as the first focusable element on the page. It should be visually hidden by default and become visible when focused (via keyboard Tab), so keyboard users can jump straight past repeated navigation without it cluttering the page for everyone else.

```jsx
function SkipLink() {
  return (
    <a
      href="#main-content"
      className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:bg-teal-600 focus:text-white focus:px-4 focus:py-2 focus:rounded-md"
    >
      Skip to main content
    </a>
  )
}

function Page() {
  return (
    <>
      <SkipLink />
      <header>{/* nav with many links */}</header>
      <main id="main-content" tabIndex={-1}>
        {/* page content */}
      </main>
    </>
  )
}
```

`tabIndex={-1}` on the target lets it receive programmatic focus when the skip link is activated, without adding `<main>` itself to the normal Tab order.

## Auditing existing code

Check every interactive element and layout against all five rules: does anything set `outline: none` (or equivalent) without a compliant replacement (rule 1)? Does any focus styling use `:focus` where `:focus-visible` would avoid showing the ring on mouse clicks (rule 2)? Is any layout visually reordered with CSS `order`/positioning without matching DOM order (rule 3)? Does any modal/dialog fail to trap Tab or fail to return focus to its trigger on close (rule 4)? Is there no skip-to-main-content link on pages with substantial navigation (rule 5)? Report each violation with the specific code involved and the fix, then implement the fixes.

## References

- `references/implementation-guide.md` -- full worked examples for all 5 rules in React/plain CSS.
- `references/audit-checklist.md` -- a rule-by-rule checklist for reviewing existing components.
