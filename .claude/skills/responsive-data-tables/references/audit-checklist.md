# Responsive table audit checklist

## Move 1: Rank

- [ ] Is there an explicit priority/classification per column (identity, value, state, metadata), or does the compact view just keep "whichever columns happen to fit" based on original desktop order?
- [ ] Do identity and value (the row's name and its key number) always survive in the compact view?

## Move 2: Stack

- [ ] Does the table use horizontal scroll on narrow screens, cutting rows in half? If so, that's the primary tell this pattern needs to be applied.
- [ ] Does the compact row layout put identity/value on one line and state/secondary info on a second line, rather than trying to cram a full row into one line at a smaller font size?

## Move 3: Slot

- [ ] Do amounts use `tabular-nums` (or equivalent) so digits align visually across rows, not just proportional-width numerals that happen to be right-aligned?
- [ ] Is the amount's horizontal position exactly consistent row to row?

## Move 4: Label

- [ ] Does every ambiguous value (dates especially) carry a label or context (e.g. "Due", "Issued") rather than being a bare, unlabeled value?
- [ ] Conversely, are self-evident values (a formatted currency figure, a percentage) left unlabeled rather than cluttered with a redundant "Amount:" / "Value:" prefix?

## Move 5: Reveal

- [ ] Are lower-priority columns (hidden from the compact view) reachable at all, or genuinely dropped/inaccessible on narrow screens?
- [ ] Is the reveal mechanism an in-place expansion or a layered sheet/drawer, rather than a full page navigation that replaces the list?
- [ ] After revealing one row's detail, can the user still see (and get back to) the rest of the list without extra navigation?

## Move 6: Breakpoint

- [ ] Does the table's compact/wide switch respond to a page-level media query (`window.innerWidth` / `@media`), or to a container query scoped to the table's own wrapper?
- [ ] If this table (or a copy of the same component) is ever placed in a narrower context that isn't the full viewport (a side panel, a modal, a dashboard widget), does it still adapt correctly? A page-breakpoint-only implementation will fail this even if it looks fine on an actual phone.

## Applying fixes

Treat this as one pass across the whole table component, not six separate isolated changes -- a table with horizontal scroll "fixed" by adding tabular-nums alone (Move 3) but with no rank/stack/reveal changes still isn't usable on a narrow screen. Fixing all 6 moves together is what actually makes the table work at any width.
