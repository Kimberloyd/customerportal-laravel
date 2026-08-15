import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/motion/input';
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
                            <Button variant="tertiary" size="compact" onClick={toggleStatus}>
                                Mark {thread.status === 'open' ? 'Closed' : 'Open'}
                            </Button>
                        )}
                        {!isCustomerViewer && thread.status === 'closed' && (
                            <Button
                                variant="tertiary"
                                size="compact"
                                className="text-red-600 hover:text-red-700"
                                onClick={deleteThread}
                            >
                                Delete
                            </Button>
                        )}
                        <Button asChild variant="ghost" size="compact">
                            <Link href={route('messages.index')}>Back to Messages</Link>
                        </Button>
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
                        <Button variant="tertiary" size="compact" onClick={rotateLink}>
                            Rotate customer link
                        </Button>
                        {thread.public_link_active && (
                            <Button
                                variant="tertiary"
                                size="compact"
                                className="text-red-600 hover:text-red-700"
                                onClick={revokeLink}
                            >
                                Revoke
                            </Button>
                        )}
                    </div>
                )}

                {!isCustomerViewer && thread.is_facebook && (
                    <div className="space-y-4 rounded-lg bg-white p-4 shadow-sm">
                        <form onSubmit={submitSenderName} className="flex items-end gap-2">
                            <Input
                                label="Display name override"
                                type="text"
                                value={senderNameForm.data.sender_name}
                                onChange={(value) => senderNameForm.setData('sender_name', value)}
                            />
                            <Button type="submit" variant="tertiary" size="compact">
                                Save
                            </Button>
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
                            <Button type="submit" variant="tertiary" size="compact">
                                Save
                            </Button>
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
                            <Button type="submit" variant="primary" disabled={processing}>
                                Send Reply
                            </Button>
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
