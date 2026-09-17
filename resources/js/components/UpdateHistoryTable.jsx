import { Table } from '@/components/motion/table';
import { formatDateTime } from '@/utils/orderDisplay';

const ROW_HEIGHT = 48;

export default function UpdateHistoryTable({ activities }) {
    const columns = [
        {
            key: 'created_at',
            header: 'Date',
            width: '180px',
            cell: (row) => <span className="text-muted-foreground">{formatDateTime(row.created_at)}</span>,
        },
        {
            key: 'actor_name',
            header: 'Actor',
            width: '180px',
            cell: (row) => <span className="text-foreground">{row.actor_name ?? '—'}</span>,
        },
        {
            key: 'actor_role',
            header: 'Role',
            width: '120px',
            cell: (row) => <span className="text-muted-foreground capitalize">{row.actor_role ?? '—'}</span>,
        },
        {
            key: 'action',
            header: 'Action',
            width: '180px',
            cell: (row) => <span className="font-medium text-foreground">{row.action}</span>,
        },
        {
            key: 'details',
            header: 'Details',
            cell: (row) => <span className="line-clamp-2 whitespace-pre-wrap text-foreground">{row.details}</span>,
        },
    ];

    return (
        <Table
            data={activities}
            columns={columns}
            getRowId={(row, index) => `${row.created_at ?? 'unknown'}-${row.action}-${index}`}
            rowHeight={ROW_HEIGHT}
            height={activities.length * ROW_HEIGHT + 60}
            emptyState="No updates yet — order changes will appear here when they are recorded."
            className="border-border"
        />
    );
}
