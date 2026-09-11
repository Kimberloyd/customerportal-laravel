# Implementation guide (React + Tailwind)

A single invoices table, applying all 6 moves together.

## Column priority definitions (Move 1)

```jsx
// priority 1 = always visible in compact view (identity + value)
// priority 2 = visible in compact view when space allows (state)
// priority 3 = hidden in compact view, reachable via row expansion (Move 5)
const columns = [
  { key: 'customer', label: 'Customer', priority: 1, role: 'identity' },
  { key: 'amount', label: 'Amount', priority: 1, role: 'value' },
  { key: 'status', label: 'Status', priority: 2, role: 'state' },
  { key: 'dueDate', label: 'Due date', priority: 2, role: 'state' },
  { key: 'invoiceId', label: 'Invoice', priority: 3, role: 'metadata' },
  { key: 'issuedDate', label: 'Issued', priority: 3, role: 'metadata' },
  { key: 'plan', label: 'Plan', priority: 3, role: 'metadata' },
];
```

## Compact row: stack + slot + label (Moves 2, 3, 4)

```jsx
function CompactRow({ invoice, expanded, onToggle }) {
  return (
    <div className="border-b border-neutral-800">
      <button
        onClick={onToggle}
        className="w-full flex items-center gap-3 py-3 text-left"
      >
        <Avatar initials={invoice.customerInitials} />
        <div className="flex-1 min-w-0">
          {/* Line 1: identity (left) + amount (right), in one consistent slot */}
          <div className="flex items-baseline justify-between gap-2">
            <span className="font-medium truncate">{invoice.customer}</span>
            <span className="tabular-nums font-semibold shrink-0">
              {formatCurrency(invoice.amount)}
              {/* No "Amount:" label -- a formatted dollar figure is self-evident (Move 4) */}
            </span>
          </div>
          {/* Line 2: state -- due date DOES get a label, it's ambiguous without one (Move 4) */}
          <div className="flex items-center gap-2 text-sm text-neutral-400 mt-0.5">
            <StatusBadge status={invoice.status} />
            <span>Due {formatDate(invoice.dueDate)}</span>
          </div>
        </div>
        <ChevronDown className={expanded ? 'rotate-180' : ''} />
      </button>

      {expanded && <ExpandedFields invoice={invoice} />}
    </div>
  );
}
```

Amount alignment (Move 3): every amount in the list renders at the same right edge with `tabular-nums`, so the column of numbers is visually real, not just "each number happens to be right-aligned":

```css
.amount {
  font-variant-numeric: tabular-nums; /* or Tailwind's `tabular-nums` class */
  text-align: right;
}
```

## Reveal: expand in place (Move 5)

```jsx
function ExpandedFields({ invoice }) {
  // The priority-3 columns from the column definitions above -- hidden from
  // the compact row, but reachable here, not deleted.
  return (
    <div className="px-3 pb-3 pt-1 text-sm space-y-1 bg-neutral-900/40">
      <Field label="Invoice" value={invoice.invoiceId} />
      <Field label="Issued" value={formatDate(invoice.issuedDate)} />
      <Field label="Plan" value={invoice.plan} />
      <div className="flex gap-2 pt-2">
        <button className="text-sm px-3 py-1.5 rounded-lg border border-neutral-700">
          Send reminder
        </button>
        <button className="text-sm px-3 py-1.5 rounded-lg bg-emerald-500 text-neutral-950 font-medium">
          Mark paid
        </button>
      </div>
    </div>
  );
}
```

An alternative to expand-in-place is a detail sheet/drawer (a `<Sheet>`/modal component layered over the list on tap) -- either is fine; a full page navigation away from the list is what to avoid.

## Container query breakpoint (Move 6)

```jsx
// Wrapper owns a container context
function InvoicesTable({ invoices }) {
  return (
    <div className="table-container" style={{ containerType: 'inline-size' }}>
      <table className="wide-table">...</table>
      <div className="compact-list">...</div>
    </div>
  );
}
```

```css
.table-container {
  container-type: inline-size;
  container-name: invoices-table;
}

/* Wide layout is the default */
.compact-list { display: none; }

/* Switches based on the container's own width, not the viewport's */
@container invoices-table (max-width: 700px) {
  .wide-table { display: none; }
  .compact-list { display: block; }
}
```

With Tailwind v4's container query support (`@container` variants), the equivalent is:

```jsx
<div className="@container">
  <table className="hidden @[700px]:table">...</table>
  <div className="block @[700px]:hidden">...</div>
</div>
```

This means the same table component correctly switches to the compact layout whether it's full-width on a phone, or placed in a 400px-wide dashboard side panel on a desktop-width viewport -- the component reacts to its own box, not `window.innerWidth`.
