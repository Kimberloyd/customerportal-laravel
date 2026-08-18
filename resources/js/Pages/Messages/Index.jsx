import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Table } from '@/components/motion/table';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/utils/orderDisplay';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const STATUS_OPTIONS = [
    { value: 'all', label: 'All' },
    { value: 'unread', label: 'Unread' },
    { value: 'open', label: 'Open' },
    { value: 'closed', label: 'Closed' },
];

export default function Index({ threads, filters }) {
    const [status, setStatus] = useState(filters.status);

    const applyFilter = (value) => {
        setStatus(value);
        router.get(route('messages.index'), { status: value }, { preserveState: true });
    };

    const columns = useMemo(
        () => [
            {
                key: 'customer_name',
                header: 'Conversation',
                cell: (thread) => (
                    <span className={thread.has_unread ? 'font-semibold' : ''}>
                        <Link href={route('messages.show', thread.id)} className="text-indigo-600 hover:underline">
                            {thread.customer_name}
                        </Link>
                        {thread.is_facebook && (
                            <span className="ml-2 rounded-full bg-blue-50 px-2 py-0.5 text-xs font-normal text-blue-700">
                                Facebook Messenger
                            </span>
                        )}
                    </span>
                ),
            },
            { key: 'subject', header: 'Subject' },
            {
                key: 'latest_preview',
                header: 'Latest Message',
                cell: (thread) => (
                    <span className="block max-w-xs truncate text-gray-600">{thread.latest_preview ?? '-'}</span>
                ),
            },
            { key: 'reply_count', header: 'Replies' },
            {
                key: 'status',
                header: 'Status',
                cell: (thread) => (
                    <span
                        className={`rounded-full px-2 py-0.5 text-xs ${
                            thread.status === 'open'
                                ? 'bg-blue-100 text-blue-800'
                                : 'bg-gray-200 text-gray-700'
                        }`}
                    >
                        {thread.status}
                    </span>
                ),
            },
            {
                key: 'updated_at',
                header: 'Updated',
                cell: (thread) => formatDateTime(thread.updated_at),
            },
        ],
        [],
    );

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Messages
                    </h2>
                    <Button asChild variant="primary">
                        <Link href={route('messages.create')}>New Conversation</Link>
                    </Button>
                </div>
            }
        >
            <Head title="Messages" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <div className="flex gap-2 rounded-lg bg-white p-4 shadow-sm">
                    {STATUS_OPTIONS.map((opt) => (
                        <Button
                            key={opt.value}
                            variant="tertiary"
                            size="compact"
                            active={status === opt.value}
                            onClick={() => applyFilter(opt.value)}
                        >
                            {opt.label}
                        </Button>
                    ))}
                </div>

                <>
                    <Table
                        data={threads.data}
                        columns={columns}
                        getRowId={(thread) => String(thread.id)}
                        resizable
                        reorderable
                        emptyState="No conversations match this filter."
                    />

                    {threads.last_page > 1 && (
                        <nav className="mt-4 flex flex-wrap items-center gap-1 text-sm">
                            {threads.links.map((link, index) => (
                                <Link
                                    key={index}
                                    href={link.url ?? '#'}
                                    preserveScroll
                                    className={`rounded px-3 py-1 ${
                                        link.active
                                            ? 'bg-gray-800 text-white'
                                            : link.url
                                              ? 'text-gray-600 hover:bg-gray-100'
                                              : 'cursor-not-allowed text-gray-300'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </nav>
                    )}
                </>
            </div>
        </AuthenticatedLayout>
    );
}
