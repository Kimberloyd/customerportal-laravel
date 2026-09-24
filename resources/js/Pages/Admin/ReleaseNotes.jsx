import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ReleaseNotesPanel } from '@/components/ReleaseNotesPanel';
import { Head, Link } from '@inertiajs/react';

export default function ReleaseNotes({ releaseNotes }) {
    return (
        <AuthenticatedLayout
            header={
                <nav aria-label="Breadcrumb">
                    <h2 className="flex items-center gap-2 text-xl font-semibold leading-tight">
                        <Link
                            href={route('admin.dashboard')}
                            className="text-muted-foreground transition-colors hover:text-primary"
                        >
                            Admin
                        </Link>
                        <span aria-hidden="true" className="text-muted-foreground">/</span>
                        <span aria-current="page" className="text-foreground">Release notes</span>
                    </h2>
                </nav>
            }
        >
            <Head title="Release notes" />

            <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
                <ReleaseNotesPanel releaseNotes={releaseNotes} />
            </div>
        </AuthenticatedLayout>
    );
}
