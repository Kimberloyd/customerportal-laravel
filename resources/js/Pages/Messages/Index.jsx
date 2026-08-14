import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDateTime } from '@/utils/orderDisplay';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

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

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Messages
                    </h2>
                    <Link
                        href={route('messages.create')}
                        className="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                    >
                        New Conversation
                    </Link>
                </div>
            }
        >
            <Head title="Messages" />

            <div className="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <div className="flex gap-2 rounded-lg bg-white p-4 shadow-sm">
                    {STATUS_OPTIONS.map((opt) => (
                        <button
                            key={opt.value}
                            onClick={() => applyFilter(opt.value)}
                            className={`rounded-md px-3 py-1.5 text-sm font-medium ${
                                status === opt.value
                                    ? 'bg-gray-800 text-white'
                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                            }`}
                        >
                            {opt.label}
                        </button>
                    ))}
                </div>

                <div className="rounded-lg bg-white p-4 shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr className="text-left text-gray-500">
                                    <th className="py-2 pr-4">Conversation</th>
                                    <th className="py-2 pr-4">Subject</th>
                                    <th className="py-2 pr-4">Latest Message</th>
                                    <th className="py-2 pr-4">Replies</th>
                                    <th className="py-2 pr-4">Status</th>
                                    <th className="py-2 pr-4">Updated</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {threads.data.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="py-4 text-center text-gray-400">
                                            No conversations match this filter.
                                        </td>
                                    </tr>
                                )}
                                {threads.data.map((thread) => (
                                    <tr key={thread.id} className={thread.has_unread ? 'font-semibold' : ''}>
                                        <td className="py-2 pr-4">
                                            <Link href={route('messages.show', thread.id)} className="text-indigo-600 hover:underline">
                                                {thread.customer_name}
                                            </Link>
                                            {thread.is_facebook && (
                                                <span className="ml-2 rounded-full bg-blue-50 px-2 py-0.5 text-xs font-normal text-blue-700">
                                                    Facebook Messenger
                                                </span>
                                            )}
                                        </td>
                                        <td className="py-2 pr-4 font-normal">{thread.subject}</td>
                                        <td className="max-w-xs truncate py-2 pr-4 font-normal text-gray-600">
                                            {thread.latest_preview ?? '-'}
                                        </td>
                                        <td className="py-2 pr-4 font-normal">{thread.reply_count}</td>
                                        <td className="py-2 pr-4 font-normal">
                                            <span
                                                className={`rounded-full px-2 py-0.5 text-xs ${
                                                    thread.status === 'open'
                                                        ? 'bg-blue-100 text-blue-800'
                                                        : 'bg-gray-200 text-gray-700'
                                                }`}
                                            >
                                                {thread.status}
                                            </span>
                                        </td>
                                        <td className="py-2 pr-4 font-normal">{formatDateTime(thread.updated_at)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

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
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
