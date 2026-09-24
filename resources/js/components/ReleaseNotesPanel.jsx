import ConfirmationDialog from '@/components/ConfirmationDialog';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/utils/orderDisplay';
import { useForm } from '@inertiajs/react';
import { Megaphone, Trash2 } from 'lucide-react';
import { useState } from 'react';

function noteLines(body) {
    return body.split('\n').map((line) => line.trim()).filter(Boolean);
}

export function ReleaseNotesPanel({ releaseNotes = [] }) {
    const form = useForm({ title: '', body: '' });
    const deletion = useForm({});
    const [notePendingDeletion, setNotePendingDeletion] = useState(null);

    const submit = (event) => {
        event.preventDefault();
        form.post(route('admin.release-notes.store'), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const confirmDelete = () => {
        if (!notePendingDeletion) return;

        deletion.delete(route('admin.release-notes.destroy', notePendingDeletion.public_id), {
            preserveScroll: true,
            onSuccess: () => setNotePendingDeletion(null),
        });
    };

    return (
        <div>
            <div className="mb-6">
                <h3 className="text-lg font-semibold text-foreground">Release notes</h3>
                <p className="mt-1 text-sm text-muted-foreground">
                    Publish what changed in this release. Shown to everyone on the What's New page, on both web and the app.
                </p>
            </div>

            <form onSubmit={submit} className="mb-8 rounded-xl border border-border bg-card p-5">
                <label className="block text-sm font-medium text-foreground">
                    Title
                    <input
                        value={form.data.title}
                        onChange={(event) => {
                            form.setData('title', event.target.value);
                            form.clearErrors('title');
                        }}
                        className={`mt-1 h-10 w-full rounded-md border px-3 text-sm outline-none focus-visible:ring-2 ${
                            form.errors.title
                                ? 'border-destructive/40 focus-visible:border-destructive focus-visible:ring-destructive/20'
                                : 'border-border focus-visible:border-primary focus-visible:ring-primary/20'
                        }`}
                        placeholder="e.g. Archived order details, password changes"
                        required
                    />
                </label>
                {form.errors.title && <p className="mt-1 text-sm text-destructive" role="alert">{form.errors.title}</p>}

                <label className="mt-4 block text-sm font-medium text-foreground">
                    What changed
                    <textarea
                        value={form.data.body}
                        onChange={(event) => {
                            form.setData('body', event.target.value);
                            form.clearErrors('body');
                        }}
                        rows={4}
                        className={`mt-1 w-full resize-none rounded-md border px-3 py-2 text-sm outline-none focus-visible:ring-2 ${
                            form.errors.body
                                ? 'border-destructive/40 focus-visible:border-destructive focus-visible:ring-destructive/20'
                                : 'border-border focus-visible:border-primary focus-visible:ring-primary/20'
                        }`}
                        placeholder={'One line per note, for example:\nOpen an archived order to view or restore it\nFixed order search sometimes missing recent orders'}
                        required
                    />
                </label>
                {form.errors.body && <p className="mt-1 text-sm text-destructive" role="alert">{form.errors.body}</p>}

                <div className="mt-4 flex justify-end">
                    <Button type="submit" variant="primary" loading={form.processing}>
                        Publish
                    </Button>
                </div>
            </form>

            {releaseNotes.length === 0 ? (
                <div className="flex flex-col items-center gap-1 rounded-xl border border-border bg-card py-10">
                    <span className="mb-2 grid h-12 w-12 place-items-center rounded-full bg-muted text-muted-foreground">
                        <Megaphone className="h-6 w-6" aria-hidden="true" />
                    </span>
                    <p className="text-sm font-medium text-foreground">No release notes published yet</p>
                    <p className="max-w-xs text-center text-sm leading-6 text-muted-foreground">
                        Publish one above to let everyone know what changed.
                    </p>
                </div>
            ) : (
                <ul className="space-y-3">
                    {releaseNotes.map((note) => (
                        <li key={note.public_id} className="rounded-xl border border-border bg-card p-5">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        Version {note.version} · {formatDateTime(note.published_at)}
                                    </p>
                                    <h4 className="mt-0.5 text-base font-semibold text-foreground">{note.title}</h4>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setNotePendingDeletion(note)}
                                    aria-label={`Delete release note v${note.version}`}
                                    className="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                >
                                    <Trash2 className="h-4 w-4" aria-hidden="true" />
                                </button>
                            </div>
                            <ul className="mt-3 list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                                {noteLines(note.body).map((line, index) => (
                                    <li key={index}>{line}</li>
                                ))}
                            </ul>
                        </li>
                    ))}
                </ul>
            )}

            <ConfirmationDialog
                open={notePendingDeletion !== null}
                onOpenChange={(nextOpen) => !nextOpen && setNotePendingDeletion(null)}
                title={`Delete release note v${notePendingDeletion?.version ?? ''}?`}
                description="This removes it from the What's New page for everyone."
                confirmLabel="Delete"
                cancelLabel="Keep it"
                onConfirm={confirmDelete}
                destructive
                processing={deletion.processing}
            />
        </div>
    );
}
