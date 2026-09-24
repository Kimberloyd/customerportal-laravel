import ConfirmationDialog from '@/components/ConfirmationDialog';
import { formatDateTime } from '@/utils/orderDisplay';
import { useForm } from '@inertiajs/react';
import { Megaphone, Trash2 } from 'lucide-react';
import { useState } from 'react';

function noteLines(body) {
    return body.split('\n').map((line) => line.trim()).filter(Boolean);
}

export function ReleaseNotesPanel({ releaseNotes = [] }) {
    const deletion = useForm({});
    const [notePendingDeletion, setNotePendingDeletion] = useState(null);

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
                    Published automatically with each release, shown to everyone on the What's New page (web and app). Remove an entry here if one needs correcting.
                </p>
            </div>

            {releaseNotes.length === 0 ? (
                <div className="flex flex-col items-center gap-1 rounded-xl border border-border bg-card py-10">
                    <span className="mb-2 grid h-12 w-12 place-items-center rounded-full bg-muted text-muted-foreground">
                        <Megaphone className="h-6 w-6" aria-hidden="true" />
                    </span>
                    <p className="text-sm font-medium text-foreground">No release notes yet</p>
                    <p className="max-w-xs text-center text-sm leading-6 text-muted-foreground">
                        These appear here as soon as the next release is published.
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
