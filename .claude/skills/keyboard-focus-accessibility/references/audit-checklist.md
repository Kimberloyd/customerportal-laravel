# Keyboard Focus Audit Checklist

Walk every interactive element and layout in the component(s) under review against these five checks. For each hit, cite the actual code (selector, class, property) and the specific fix.

## 1. Focus Ring
- [ ] Does any CSS set `outline: none` / `outline: 0` on a focusable element (button, link, input, custom widget)?
- [ ] If so, is there a compliant replacement ring defined elsewhere (on `:focus` or `:focus-visible`)? A removal with no replacement is a WCAG 2.4.7 failure -- flag it.
- [ ] Where a ring exists, is it at least 2px wide, offset ~2px from the element, and does it clear 3:1 contrast against the surface(s) it appears on (check both light and dark contexts if the component supports both)?

## 2. :focus-visible
- [ ] Is focus styling keyed off `:focus` (fires on mouse click too) instead of `:focus-visible` (keyboard/programmatic only)?
- [ ] Flag any component where clicking with a mouse shows a focus ring that a keyboard-only design intended only for Tab navigation.

## 3. Tab Order
- [ ] Does any layout use CSS `order` (flex/grid), `float`, or absolute positioning to visually reorder a set of focusable elements?
- [ ] If so, does the DOM order match what's rendered? Tab through the layout mentally (or check the source order) and compare it to the visual left-to-right/top-to-bottom order. Flag any mismatch.

## 4. Focus Trap
- [ ] Does the codebase have any modal/dialog/drawer component?
- [ ] Does Tab (and Shift+Tab) stay confined to the modal's own focusable elements, or can it escape to the page behind it?
- [ ] Does Escape close the modal? Does closing (via Escape, the X button, or Cancel) return focus to the element that originally opened it, or does focus get lost (falls back to `<body>`)?

## 5. Skip Link
- [ ] Does the page have a "Skip to main content" (or equivalent) link as the first focusable element?
- [ ] Is it visually hidden by default and does it become visible when focused via keyboard?
- [ ] Does its target (`#main-content` or similar) exist and have `tabIndex={-1}` so it can actually receive focus when the link is activated?
- [ ] Flag its absence on any page with substantial repeated navigation (nav bars, sidebars) before the main content.

## Reporting format

For each violation found, state: the element/file, the specific code involved (e.g. "`button:focus { outline: none; }` in `styles.css`, no `:focus-visible` replacement anywhere in the file"), which rule it violates, and the concrete fix. Then implement the fix, don't just report it.
