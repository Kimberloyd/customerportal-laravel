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
        <main className="min-h-screen bg-muted py-10 dark:bg-background">
            <Head title={thread.subject} />

            <div className="mx-auto max-w-2xl space-y-4 px-4">
                <h1 className="text-xl font-semibold text-foreground">{thread.subject}</h1>

                {flash?.success && (
                    <div className="rounded-md border border-success/20 bg-success/10 p-3 text-sm text-success" role="status" aria-live="polite">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="rounded-md border border-destructive/20 bg-destructive/10 p-3 text-sm text-destructive" role="alert">
                        {flash.error}
                    </div>
                )}

                <div className="space-y-3 rounded-lg border border-border bg-card p-4">
                    {messages.length === 0 && (
                        <p className="text-sm text-muted-foreground">No messages yet. Replies will appear here.</p>
                    )}
                    {messages.map((message) => (
                        <div
                            key={message.id}
                            className={`max-w-lg rounded-lg p-3 text-sm ${
                                message.sender_type === 'customer'
                                    ? 'ml-auto bg-primary/10 text-foreground'
                                    : 'bg-muted text-foreground'
                            }`}
                        >
                            <div className="whitespace-pre-wrap">{message.body}</div>
                            <div className="mt-1 text-xs text-muted-foreground">
                                {message.sender_type === 'customer' ? 'You' : 'Company'} · {formatDateTime(message.created_at)}
                            </div>
                        </div>
                    ))}
                </div>

                {canReply ? (
                    <form onSubmit={submit} className="space-y-2 rounded-lg border border-border bg-card p-4">
                        <label htmlFor="reply-body" className="sr-only">
                            Write your reply
                        </label>
                        <textarea
                            id="reply-body"
                            required
                            rows={3}
                            value={data.body}
                            onChange={(e) => setData('body', e.target.value)}
                            placeholder="Write your reply"
                            className="block w-full rounded-md border-border bg-transparent text-sm text-foreground placeholder:text-muted-foreground/60"
                        />
                        <div className="flex justify-end">
                            <Button type="submit" variant="primary" loading={processing}>
                                Send Reply
                            </Button>
                        </div>
                    </form>
                ) : (
                    <div className="rounded-lg bg-muted p-4 text-sm text-muted-foreground">
                        This conversation is closed.
                    </div>
                )}
            </div>
        </main>
    );
}
