import { Button } from '@/components/ui/button';
import { Head, useForm, usePage } from '@inertiajs/react';

function formatDateTime(iso) {
    if (!iso) return '-';
    return new Date(iso).toISOString().slice(0, 16).replace('T', ' ');
}

export default function Show({ token, thread, messages }) {
    const { flash } = usePage().props;
    const { data, setData, post, processing, reset } = useForm({ body: '' });
    const canReply = thread.status === 'open';

    const submit = (e) => {
        e.preventDefault();
        post(route('messages.customer-conversation.reply', token), {
            onSuccess: () => reset('body'),
        });
    };

    return (
        <div className="min-h-screen bg-gray-100 py-10">
            <Head title={thread.subject} />

            <div className="mx-auto max-w-2xl space-y-4 px-4">
                <h1 className="text-xl font-semibold text-gray-900">{thread.subject}</h1>

                {flash?.success && (
                    <div className="rounded-md bg-green-50 p-3 text-sm text-green-700">{flash.success}</div>
                )}
                {flash?.error && (
                    <div className="rounded-md bg-red-50 p-3 text-sm text-red-700">{flash.error}</div>
                )}

                <div className="space-y-3 rounded-lg bg-white p-4 shadow-sm">
                    {messages.length === 0 && <p className="text-sm text-gray-400">No messages yet.</p>}
                    {messages.map((message) => (
                        <div
                            key={message.id}
                            className={`max-w-lg rounded-lg p-3 text-sm ${
                                message.sender_type === 'customer'
                                    ? 'ml-auto bg-indigo-50 text-indigo-900'
                                    : 'bg-gray-100 text-gray-900'
                            }`}
                        >
                            <div className="whitespace-pre-wrap">{message.body}</div>
                            <div className="mt-1 text-xs text-gray-500">
                                {message.sender_type === 'customer' ? 'You' : 'Company'} · {formatDateTime(message.created_at)}
                            </div>
                        </div>
                    ))}
                </div>

                {canReply ? (
                    <form onSubmit={submit} className="space-y-2 rounded-lg bg-white p-4 shadow-sm">
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
                        This conversation is closed.
                    </div>
                )}
            </div>
        </div>
    );
}
