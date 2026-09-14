import { Table } from '@/components/motion/table';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/utils/orderDisplay';

const REMARKS_ROW_HEIGHT = 48;
const TOTAL_COLUMNS = 4;

/**
 * Every audit row already snapshots the order's remarks value at that
 * moment (see OrderAudit::record), regardless of what the row's own
 * action/details are about -- so a "remarks changed" history can be
 * derived by walking the log chronologically and keeping only the
 * points where that snapshot actually differs from the one before it,
 * rather than depending on any particular action/details wording.
 */
function buildRemarksRows(auditLogs, order) {
    const chronological = [...auditLogs].reverse();
    const rows = [];
    let previous;

    for (const log of chronological) {
        const value = log.remarks ?? null;
        if (value && value !== previous) {
            rows.push({
                id: `${log.created_at ?? 'unknown'}-${rows.length}`,
                created_at: log.created_at,
                actor_name: log.actor_name,
                actor_role: log.actor_role,
                remarks: value,
            });
        }
        previous = value;
    }

    // No audit row ever carried this order's current remarks (e.g. it was
    // set before audit logging existed) -- still show it as the one row
    // rather than silently dropping a remark that's genuinely there.
    if (rows.length === 0 && order.remarks) {
        rows.push({
            id: 'initial',
            created_at: order.submitted_at,
            actor_name: null,
            actor_role: null,
            remarks: order.remarks,
        });
    }

    // Built oldest-to-newest above -- that order is what makes "value
    // actually changed from the one before it" detectable in the first
    // place -- but latest belongs at the top for display, same as every
    // other table on this page.
    return rows.reverse();
}

export default function RemarksTimeline({ order, form, onSave, canEdit }) {
    const rows = buildRemarksRows(order.audit_logs ?? [], order);

    if (canEdit) {
        rows.push({ id: '__add-remark__', __isInput: true });
    }

    // Shared by the desktop in-table row and the mobile-only duplicate
    // rendered below the table -- position:sticky doesn't reliably stick
    // inside a real <table> across browsers (confirmed broken, not just
    // untried), so on narrow viewports this control needs to live outside
    // the table's own horizontal scroll area entirely.
    const renderAddRemarkControl = () => (
        <>
            <input
                type="text"
                value={form.data.remarks}
                onChange={(e) => form.setData('remarks', e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' && form.data.remarks.trim()) onSave();
                }}
                maxLength={5000}
                placeholder="Add a remark…"
                className="min-w-0 flex-1 rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
            />
            <Button
                type="button"
                variant="primary"
                size="compact"
                className="shrink-0 rounded-md"
                onClick={onSave}
                disabled={form.processing || !form.data.remarks.trim()}
            >
                Add remark
            </Button>
        </>
    );

    const columns = [
        {
            key: 'created_at',
            header: 'Date',
            width: '180px',
            spanRow: (row) => (row.__isInput ? TOTAL_COLUMNS : undefined),
            cell: (row) =>
                row.__isInput ? (
                    <div className="hidden w-[min(70vw,32rem)] items-center gap-2 md:flex">
                        {renderAddRemarkControl()}
                    </div>
                ) : (
                    <span className="text-gray-500">{formatDateTime(row.created_at)}</span>
                ),
        },
        {
            key: 'actor_name',
            header: 'Added By',
            width: '180px',
            cell: (row) => (row.__isInput ? null : <span className="text-gray-900">{row.actor_name ?? '—'}</span>),
        },
        {
            key: 'actor_role',
            header: 'Role',
            width: '120px',
            cell: (row) =>
                row.__isInput ? null : <span className="text-gray-500 capitalize">{row.actor_role ?? '—'}</span>,
        },
        {
            key: 'remarks',
            header: 'Remark',
            cell: (row) =>
                row.__isInput ? null : (
                    <span className="line-clamp-2 whitespace-pre-wrap text-gray-900">{row.remarks}</span>
                ),
        },
    ];

    return (
        <div>
            <div className="mb-3">
                <h3 className="text-lg font-semibold text-gray-900">Remarks</h3>
                <p className="text-sm text-gray-500">Notes recorded on this order, in the order they were added.</p>
            </div>
            {form.errors.remarks && <p className="mb-2 text-sm text-red-600">{form.errors.remarks}</p>}
            <Table
                data={rows}
                columns={columns}
                getRowId={(row) => row.id}
                rowHeight={REMARKS_ROW_HEIGHT}
                height={rows.length * REMARKS_ROW_HEIGHT + 60}
                emptyState="Nothing has been noted on this order."
                className="border-gray-200"
            />
            {canEdit && (
                <div className="mt-3 flex items-center gap-2 md:hidden">{renderAddRemarkControl()}</div>
            )}
        </div>
    );
}
