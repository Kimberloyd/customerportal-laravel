import { Table } from '@/components/motion/table';
import { formatDateTime } from '@/utils/orderDisplay';

const ROW_HEIGHT = 48;

export default function UpdateHistoryTable({ activities }) {
    const columns = [
        {
            key: 'created_at',
            header: 'Date',
            width: '180px',
            cell: (row) => <span className="text-gray-500 dark:text-gray-400">{formatDateTime(row.created_at)}</span>,
        },
        {
            key: 'actor_name',
            header: 'Actor',
            width: '180px',
            cell: (row) => <span className="text-gray-900 dark:text-gray-100">{row.actor_name ?? '—'}</span>,
        },
        {
            key: 'actor_role',
            header: 'Role',
            width: '120px',
            cell: (row) => <span className="text-gray-500 capitalize dark:text-gray-400">{row.actor_role ?? '—'}</span>,
        },
        {
            key: 'action',
            header: 'Action',
            width: '180px',
            cell: (row) => <span className="font-medium text-gray-900 dark:text-gray-100">{row.action}</span>,
        },
        {
            key: 'details',
            header: 'Details',
            cell: (row) => <span className="line-clamp-2 whitespace-pre-wrap text-gray-900 dark:text-gray-100">{row.details}</span>,
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
            className="border-gray-200 dark:border-white/10"
        />
    );
}
