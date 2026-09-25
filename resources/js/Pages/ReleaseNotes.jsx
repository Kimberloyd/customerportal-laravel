import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/utils/orderDisplay';
import { Head, Link } from '@inertiajs/react';
import { Megaphone } from 'lucide-react';

function noteLines(body) {
    return body.split('\n').map((line) => line.trim()).filter(Boolean);
}

export default function ReleaseNotes({ releases = [] }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="type-page-heading text-foreground">
                    Release Notes
                </h2>
            }
        >
            <Head title="Release Notes" />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <section className="overflow-hidden rounded-2xl border border-border bg-card px-6 py-8 text-center sm:px-8 sm:py-10">
                    <h1 className="text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">
                        What's changed in the portal
                    </h1>
                    <p className="mx-auto mt-3 max-w-2xl type-body text-muted-foreground sm:text-lg">
                        A running log of updates to this app, on the web and in the mobile app.
                    </p>
                </section>

                {releases.length === 0 ? (
                    <div className="mt-8 flex flex-col items-center gap-2 rounded-xl border border-border bg-card py-14">
                        <span className="mb-1 grid h-12 w-12 place-items-center rounded-full bg-muted text-muted-foreground">
                            <Megaphone className="h-6 w-6" aria-hidden="true" />
                        </span>
                        <p className="text-sm font-medium text-foreground">Nothing published yet</p>
                        <p className="max-w-xs text-center text-sm leading-6 text-muted-foreground">
                            Check back after the next update.
                        </p>
                    </div>
                ) : (
                    <ol className="mt-8 space-y-6">
                        {releases.map((release) => (
                            <li key={release.version} className="rounded-xl border border-border bg-card p-6">
                                <div className="sm:flex sm:items-start sm:justify-between sm:gap-4">
                                    <h2 className="type-section-heading text-foreground">{release.title}</h2>
                                    <p className="mt-1 type-label text-muted-foreground sm:mt-0 sm:shrink-0 sm:text-right">
                                        Version {release.version} · {formatDateTime(release.published_at)}
                                    </p>
                                </div>
                                <ul className="mt-3 list-disc space-y-1.5 pl-5 type-body text-muted-foreground">
                                    {noteLines(release.body).map((line, index) => (
                                        <li key={index}>{line}</li>
                                    ))}
                                </ul>
                            </li>
                        ))}
                    </ol>
                )}

                <section className="mt-8 rounded-xl border border-border bg-card p-5 sm:flex sm:items-center sm:justify-between sm:gap-6">
                    <div>
                        <h2 className="type-section-heading text-foreground">Questions about a change?</h2>
                        <p className="mt-1 type-body text-muted-foreground">
                            Use the message icon in the header to contact the Theomeds team.
                        </p>
                    </div>
                    <Button asChild variant="tertiary" className="mt-4 sm:mt-0">
                        <Link href={route('dashboard')}>Return to dashboard</Link>
                    </Button>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
