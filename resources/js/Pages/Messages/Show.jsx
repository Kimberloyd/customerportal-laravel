import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDateTime } from '@/utils/orderDisplay';
import { Head, Link, router, useForm } from '@inertiajs/react';

export default function Show({ thread, messages, isCustomerViewer, facebookCustomers }) {
    const canReply = thread.status === 'open' && !(isCustomerViewer && thread.is_facebook);

    const { data, setData, post, processing, reset } = useForm({ body: '' });
    const senderNameForm = useForm({ sender_name: thread.external_sender_name ?? '' });
    const customerLinkForm = useForm({ customer_id: thread.customer_id ?? '' });

    const submitReply = (e) => {
        e.preventDefault();
        post(route('messages.reply', thread.id), { onSuccess: () => reset('body') });
    };

    const toggleStatus = () => {
        router.post(route('messages.status', thread.id), {
            status: thread.status === 'open' ? 'closed' : 'open',
        });
    };

    const deleteThread = () => {
        if (!confirm('Delete this closed conversation permanently?')) return;
        router.post(route('messages.destroy', thread.id));
    };

    const rotateLink = () => {
        router.post(route('messages.public-link', thread.id), { action: 'rotate' });
    };

    const revokeLink = () => {
        if (!confirm('Revoke the current customer link? It will stop working immediately.')) return;
        router.post(route('messages.public-link', thread.id), { action: 'revoke' });
    };

    const submitSenderName = (e) => {
        e.preventDefault();
        senderNameForm.post(route('messages.sender-name', thread.id));
    };

    const submitCustomerLink = (e) => {
        e.preventDefault();
        customerLinkForm.post(route('messages.customer-link', thread.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        {thread.subject}
                    </h2>
                    <div className="flex flex-wrap gap-2">
                        {!isCustomerViewer && (
                            <button
                                onClick={toggleStatus}
                                className="rounded-md bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200"
                            >
                                Mark {thread.status === 'open' ? 'Closed' : 'Open'}
                            </button>
                        )}
                        {!isCustomerViewer && thread.status === 'closed' && (
                            <button
                                onClick={deleteThread}
                                className="rounded-md bg-red-50 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-100"
                            >
                                Delete
                            </button>
                        )}
                        <Link href={route('messages.index')} className="text-sm text-gray-600 hover:underline">
                            Back to Messages
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title={thread.subject} />

            <div className="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <div className="rounded-lg bg-white p-4 shadow-sm">
                    <p className="text-sm text-gray-600">
                        <strong>Conversation with:</strong> {thread.customer_name}
                        {thread.is_facebook && (
                            <span className="ml-2 rounded-full bg-blue-50 px-2 py-0.5 text-xs text-blue-700">
                                Facebook Messenger
                            </span>
                        )}
                    </p>
                </div>

                {!isCustomerViewer && !thread.is_facebook && (
                    <div className="flex flex-wrap items-center gap-2 rounded-lg bg-white p-4 shadow-sm">
                        <span className="text-sm text-gray-600">
                            Guest link: {thread.public_link_active ? 'active' : 'inactive'}
                        </span>
                        <button onClick={rotateLink} className="rounded-md bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200">
                            Rotate customer link
                        </button>
                        {thread.public_link_active && (
                            <button onClick={revokeLink} className="rounded-md bg-red-50 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-100">
                                Revoke
                            </button>
                        )}
                    </div>
                )}

                {!isCustomerViewer && thread.is_facebook && (
                    <div className="space-y-4 rounded-lg bg-white p-4 shadow-sm">
                        <form onSubmit={submitSenderName} className="flex items-end gap-2">
                            <label className="flex flex-col text-sm text-gray-600">
                                Display name override
                                <input
                                    type="text"
                                    value={senderNameForm.data.sender_name}
                                    onChange={(e) => senderNameForm.setData('sender_name', e.target.value)}
                                    className="mt-1 rounded-md border-gray-300 text-sm"
                                />
                            </label>
                            <button type="submit" className="rounded-md bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200">
                                Save
                            </button>
                        </form>

                        <form onSubmit={submitCustomerLink} className="flex items-end gap-2">
                            <label className="flex flex-col text-sm text-gray-600">
                                Link to customer
                                <select
                                    value={customerLinkForm.data.customer_id}
                                    onChange={(e) => customerLinkForm.setData('customer_id', e.target.value)}
                                    className="mt-1 rounded-md border-gray-300 text-sm"
                                >
                                    <option value="">Unlinked</option>
                                    {facebookCustomers.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.company_name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <button type="submit" className="rounded-md bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200">
                                Save
                            </button>
                        </form>
                    </div>
                )}

                <div className="space-y-3 rounded-lg bg-white p-4 shadow-sm">
                    {messages.length === 0 && <p className="text-sm text-gray-400">No messages yet.</p>}
                    {messages.map((message) => (
                        <div
                            key={message.id}
                            className={`max-w-lg rounded-lg p-3 text-sm ${
                                message.sender_type === 'customer'
                                    ? 'bg-gray-100 text-gray-900'
                                    : 'ml-auto bg-indigo-50 text-indigo-900'
                            }`}
                        >
                            <div className="whitespace-pre-wrap">{message.body}</div>
                            <div className="mt-1 text-xs text-gray-500">
                                {message.sender_type === 'customer' ? 'Customer' : 'Company'} · {formatDateTime(message.created_at)}
                            </div>
                        </div>
                    ))}
                </div>

                {canReply ? (
                    <form onSubmit={submitReply} className="space-y-2 rounded-lg bg-white p-4 shadow-sm">
                        <textarea
                            required
                            rows={3}
                            value={data.body}
                            onChange={(e) => setData('body', e.target.value)}
                            placeholder="Write a reply..."
                            className="block w-full rounded-md border-gray-300 text-sm"
                        />
                        <div className="flex justify-end">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
                            >
                                Send Reply
                            </button>
                        </div>
                    </form>
                ) : (
                    <div className="rounded-lg bg-gray-50 p-4 text-sm text-gray-500">
                        {thread.status === 'closed'
                            ? 'This conversation is closed. Reopen it to reply.'
                            : 'Reply in Messenger instead.'}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
