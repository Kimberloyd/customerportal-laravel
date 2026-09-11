---
name: responsive-data-tables
description: "Implements and audits responsive/adaptive data tables using a 6-part pattern: rank columns by use (identity/value/state) not original order; stack each row into two lines instead of horizontal scroll; give amounts one consistent tabular-nums slot; label ambiguous values (dates) but not self-evident ones (currency); reveal hidden columns via in-place expansion or a sheet, never a page nav; and have the table own its breakpoint via container queries, not just viewport width. Use when building or reviewing a data table/list/grid that needs to work on narrow screens or in a resizable panel -- especially AI-generated tables, which commonly just add horizontal scroll or shrink columns until unreadable. Trigger when a table looks broken/cramped on mobile, when adding a table/list view, or when asked about responsive tables, horizontal scroll on mobile, or tables in a narrow panel/sidebar."
---

# Responsive Data Tables

## The problem this fixes

The default AI-generated response to "this table doesn't fit on a phone" is either horizontal scroll (which cuts rows in half and hides half the data off-screen) or shrinking every column proportionally (which makes everything equally unreadable). Neither actually solves the problem -- they make the same table smaller instead of rethinking what the table needs to show at this width. A responsive table isn't a shrunk table; it's a table that changes shape based on what it has room for, while keeping every capability (sort, filter, drill into detail) available.

## Move 1: Rank columns by use, not position

A desktop table's column order (left to right) reflects how it was built, not what matters. Before deciding what survives on a narrow screen, classify every column:

- **Identity** -- what row is this (name, ID)? Always keeps priority 1.
- **Value** -- the number/metric this table exists to show (an amount, a count). Usually priority 1.
- **State** -- status/category (paid, overdue, draft). Usually priority 2.
- Everything else (dates, metadata, secondary fields) ranks lower and moves to a "reveal on demand" tier (see Move 5) rather than being cut or force-fit.

Direct your AI to make this ranking explicit and deliberate (e.g. a `priority` field per column definition) rather than implicitly keeping "whichever columns fit" based on original order.

## Move 2: Stack into two lines per row, not horizontal scroll

Horizontal scroll on a data table means half of every row is invisible until the user scrolls sideways -- name might be visible, but the status that made this row worth looking at might not be. Fix: restructure each row into two lines instead of one: primary identity on the left, the key value right-aligned on the same line, and secondary/state info (status, a label) on a second line below.

## Move 3: One consistent slot for the amount

Right-aligning a number isn't enough if the numbers still don't visually line up -- variable digit counts and non-monospaced figures make a "column" of numbers look ragged even when they're all nominally in the same place. Use `font-variant-numeric: tabular-nums` (or the Tailwind `tabular-nums` utility) so every digit takes the same width, and keep the amount's alignment and horizontal position exactly consistent row to row -- a real visual column, not just numbers that happen to be right-aligned.

## Move 4: Label the ambiguous, not the self-evident

A bare date ("Mar 04") is ambiguous without context -- due date? issued date? last updated? Label it ("Due Mar 04"). A formatted dollar amount ("$1,204.00") is self-evident -- it doesn't need "Amount: " in front of it. Direct your AI to make this judgment per field rather than uniformly labeling everything (which adds clutter) or uniformly labeling nothing (which leaves genuinely ambiguous values unclear).

## Move 5: Hidden isn't deleted -- reveal in place or in a sheet

Columns that don't make the cut for the compact row view (Move 1's lower-priority tier) still need to be reachable -- they're hidden, not gone. Two acceptable patterns:

- **Expand in place**: tapping a row reveals its remaining fields inline, without navigating away from the list (the row grows, the rest of the list stays visible below).
- **Detail sheet**: tapping a row opens a sheet/drawer with the full record -- still layered on top of the list, not a full page navigation that replaces it.

What to avoid: navigating to a completely separate page just to see the fields that didn't fit in the row -- that breaks the list context and makes comparing rows require repeated back-and-forth navigation.

## Move 6: The table owns its own breakpoint

A table that only responds to the page's global viewport breakpoint (a media query) looks right on a full-width mobile screen but breaks the moment it's placed in a narrower context that isn't the whole viewport -- a side panel, a modal, a dashboard widget slot. Use a container query (`@container`) scoped to the table's own wrapper instead of a page-level media query, so the table adapts to the space it's actually been given, not just the device it happens to be on.

```css
.table-container {
  container-type: inline-size;
}

@container (max-width: 700px) {
  .table { /* switch to the stacked-card layout */ }
}
```

See `references/implementation-guide.md` for concrete React/Tailwind examples of all 6 moves together, and `references/audit-checklist.md` for a systematic review procedure.

## The core principle

A responsive table isn't the same table scaled down -- it's a table that changes shape based on the priority of its own data and the space it's actually given, while keeping every column reachable and every number legible. Apply all 6 moves together; a table with horizontal scroll "fixed" by only 2 or 3 of these moves still leaves real usability gaps.
